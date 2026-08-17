from pathlib import Path

p = Path('resources/assets/js/views/invoices/Create.vue')
s = p.read_text()

replacements = {
    ':title="$tc(\'invoices.invoice\', 2)"': 'title="Cuotas y comprobantes"',
    ':title="$t(\'invoices.new_invoice\')"': 'title="Nueva cuota/comprobante"',
    "{{ $t('invoices.save_invoice') }}": 'Guardar cuota/comprobante',
    ":label=\"$t('invoices.invoice_date')\"": 'label="Fecha de emisión"',
    ":label=\"$t('invoices.due_date')\"": 'label="Fecha de vencimiento"',
    ":label=\"$t('invoices.invoice_number')\"": 'label="Número de comprobante"',
    ":label=\"$t('invoices.ref_number')\"": 'label="Referencia"',
    "{{ $tc('items.item', 2) }}": 'Conceptos y aranceles',
    "{{ $t('invoices.add_item') }}": 'Agregar concepto o arancel',
    "{{ $t('invoices.item.price') }}": 'Importe unitario',
    "{{ $t('invoices.item.amount') }}": 'Subtotal',
    "{{ $t('invoices.total') }} {{ $t('invoices.amount') }}:": 'Total a cobrar:',
}

for old, new in replacements.items():
    s = s.replace(old, new)

s = s.replace("return this.$t('invoices.edit_invoice')", "return 'Editar cuota/comprobante'")
s = s.replace("return this.$t('invoices.new_invoice')", "return 'Nueva cuota/comprobante'")

# La plantilla sigue existiendo como dato técnico compatible con Crater, pero no se expone
# como decisión del usuario en el flujo escolar.
s = s.replace(
    '<sw-input-group\n            :label="$t(\'invoices.invoice_template\')"\n            class="mt-6 mb-1"\n            required\n          >',
    '<sw-input-group\n            v-if="false"\n            :label="$t(\'invoices.invoice_template\')"\n            class="mt-6 mb-1"\n            required\n          >'
)

# Impuestos se mantienen internamente por compatibilidad, pero no se muestran en la carga
# normal de aranceles escolares. No eliminamos estructura ni cálculos históricos.
s = s.replace('<div v-if="taxPerItem ? \'NO\' : null">', '<div v-if="false">')
s = s.replace(
    '<sw-popup\n            v-if="taxPerItem === \'NO\' || taxPerItem === null"',
    '<sw-popup\n            v-if="false"'
)

p.write_text(s)
