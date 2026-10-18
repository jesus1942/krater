<?php

namespace Crater\Services\Data;

use Crater\Models\Estimate;
use Crater\Models\Expense;
use Crater\Models\Invoice;
use Crater\Models\Item;
use Crater\Models\Payment;
use Crater\Models\User;
use Crater\Services\Access\TenantUsers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fila 5: baja logica y papelera.
 *
 * Las bajas nunca borran filas: marcan deleted_at. Los scopes globales de
 * empresa y nivel siguen aplicando, asi que un ID fuera del alcance del
 * usuario no se encuentra y el lote entero se rechaza con 403.
 */
class TrashBin
{
    const TYPES = [
        'invoices' => Invoice::class,
        'estimates' => Estimate::class,
        'payments' => Payment::class,
        'expenses' => Expense::class,
        'items' => Item::class,
        'customers' => User::class,
    ];

    public function delete(string $type, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        DB::transaction(function () use ($type, $ids) {
            $models = $this->query($type)->whereIn($this->table($type).'.id', $ids)
                ->orderBy($this->table($type).'.id')->lockForUpdate()->get();
            abort_unless($models->count() === count($ids), 403);

            foreach ($models as $model) {
                $this->{'delete'.ucfirst($type)}($model);
            }
        });
    }

    public function list(string $type, int $limit = 25)
    {
        $table = $this->table($type);

        return $this->query($type)->onlyTrashed()
            ->orderByDesc($table.'.deleted_at')->orderByDesc($table.'.id')
            ->paginate($limit);
    }

    public function restore(string $type, int $id)
    {
        return DB::transaction(function () use ($type, $id) {
            $model = $this->query($type)->onlyTrashed()
                ->where($this->table($type).'.id', $id)->lockForUpdate()->first();
            abort_unless($model, 404);

            $this->{'restore'.ucfirst($type)}($model);

            return $model->fresh();
        });
    }

    protected function query(string $type): Builder
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        if ($type === 'customers') {
            return app(TenantUsers::class)->customers()->withTrashed();
        }

        return self::TYPES[$type]::withTrashed();
    }

    protected function table(string $type): string
    {
        return (new (self::TYPES[$type]))->getTable();
    }

    protected function deleteInvoices(Invoice $invoice): void
    {
        abort_if($invoice->trashed(), 404);
        // Invariante: los cobros vigentes se dan de baja antes que la factura.
        if ($invoice->payments()->exists()) {
            throw ValidationException::withMessages([
                'ids' => 'La factura '.$invoice->invoice_number.' tiene cobros vigentes: darlos de baja primero.',
            ]);
        }
        $invoice->delete();
    }

    protected function deleteEstimates(Estimate $estimate): void
    {
        abort_if($estimate->trashed(), 404);
        $estimate->delete();
    }

    protected function deleteExpenses(Expense $expense): void
    {
        abort_if($expense->trashed(), 404);
        $expense->delete();
    }

    protected function deleteItems(Item $item): void
    {
        abort_if($item->trashed(), 404);
        foreach (['invoiceItems', 'estimateItems', 'taxes'] as $relation) {
            if ($item->$relation()->exists()) {
                throw ValidationException::withMessages(['ids' => 'El item '.$item->name.' esta en uso.']);
            }
        }
        $item->delete();
    }

    /** Mismo efecto que Payment::deletePayments: el monto vuelve al saldo. */
    protected function deletePayments(Payment $payment): void
    {
        abort_if($payment->trashed(), 404);
        if ($payment->invoice_id) {
            $invoice = Invoice::withoutGlobalScope('school_level')->lockForUpdate()->find($payment->invoice_id);
            if ($invoice) {
                $invoice->due_amount = (int) $invoice->due_amount + (int) $payment->amount;
                $invoice->paid_status = $invoice->due_amount == $invoice->total
                    ? Invoice::STATUS_UNPAID
                    : Invoice::STATUS_PARTIALLY_PAID;
                $invoice->status = $invoice->getPreviousStatus();
                $invoice->save();
            }
        }
        $payment->delete();
    }

    /**
     * La familia se da de baja con sus documentos en un mismo instante; esa
     * marca permite restaurar exactamente lo que cayo con ella.
     */
    protected function deleteCustomers(User $customer): void
    {
        abort_if($customer->trashed(), 404);
        $now = Carbon::now()->startOfSecond();
        foreach (['payments', 'invoices', 'estimates'] as $relation) {
            $documents = $this->documentsOf($customer, $relation)->lockForUpdate()->get();
            // delete() deja el evento auditado; despues se iguala la marca.
            $documents->each->delete();
            $this->documentsOf($customer, $relation)->onlyTrashed()
                ->whereKey($documents->modelKeys())->update(['deleted_at' => $now]);
        }
        $customer->delete();
        User::withTrashed()->whereKey($customer->id)->update(['deleted_at' => $now]);
    }

    /** Toda la familia, en cualquier nivel: la baja de una familia es institucional. */
    protected function documentsOf(User $customer, string $relation)
    {
        return $customer->$relation()->withoutGlobalScope('school_level');
    }

    protected function restoreInvoices(Invoice $invoice): void
    {
        $this->ensureCustomerActive($invoice->user_id);
        $invoice->restore();
    }

    protected function restoreEstimates(Estimate $estimate): void
    {
        $this->ensureCustomerActive($estimate->user_id);
        $estimate->restore();
    }

    protected function restoreExpenses(Expense $expense): void
    {
        $expense->restore();
    }

    protected function restoreItems(Item $item): void
    {
        $item->restore();
    }

    /** Revierte el efecto de la baja: el monto vuelve a descontarse del saldo. */
    protected function restorePayments(Payment $payment): void
    {
        $this->ensureCustomerActive($payment->user_id);
        if ($payment->invoice_id) {
            $invoice = Invoice::withTrashed()->withoutGlobalScope('school_level')->lockForUpdate()->find($payment->invoice_id);
            if (! $invoice || $invoice->trashed()) {
                throw ValidationException::withMessages(['id' => 'Restaurar primero la factura del cobro.']);
            }
            $due = (int) $invoice->due_amount - (int) $payment->amount;
            if ($due < 0) {
                throw ValidationException::withMessages(['id' => 'El cobro supera el saldo actual de la factura.']);
            }
            $invoice->due_amount = $due;
            if ($due === 0) {
                $invoice->status = Invoice::STATUS_COMPLETED;
                $invoice->paid_status = Invoice::STATUS_PAID;
            } else {
                $invoice->status = $invoice->getPreviousStatus();
                $invoice->paid_status = Invoice::STATUS_PARTIALLY_PAID;
            }
            $invoice->save();
        }
        $payment->restore();
    }

    protected function restoreCustomers(User $customer): void
    {
        $deletedAt = $customer->deleted_at;
        $customer->restore();
        // Primero facturas, despues cobros: el cobro restaurado necesita su
        // factura vigente. No se recalculan saldos: la familia cayo entera.
        foreach (['estimates', 'invoices', 'payments'] as $relation) {
            $this->documentsOf($customer, $relation)->onlyTrashed()
                ->where($customer->$relation()->getRelated()->getTable().'.deleted_at', $deletedAt)
                ->get()->each->restore();
        }
    }

    protected function ensureCustomerActive($userId): void
    {
        if ($userId && User::onlyTrashed()->whereKey($userId)->exists()) {
            throw ValidationException::withMessages(['id' => 'Restaurar primero la familia.']);
        }
    }
}
