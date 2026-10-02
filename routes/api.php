<?php

use Crater\Http\Controllers\AppVersionController;
use Crater\Http\Controllers\V1\Auth\ForgotPasswordController;
use Crater\Http\Controllers\V1\Auth\ResetPasswordController;
use Crater\Http\Controllers\V1\Backup\BackupsController;
use Crater\Http\Controllers\V1\Backup\DownloadBackupController;
use Crater\Http\Controllers\V1\Customer\CustomersController;
use Crater\Http\Controllers\V1\Data\DataReconciliationController;
use Crater\Http\Controllers\V1\Customer\CustomerStatsController;
use Crater\Http\Controllers\V1\Billing\SchoolBillingOptionsController;
use Crater\Http\Controllers\V1\CustomField\CustomFieldsController;
use Crater\Http\Controllers\V1\Dashboard\DashboardController;
use Crater\Http\Controllers\V1\Estimate\ChangeEstimateStatusController;
use Crater\Http\Controllers\V1\Estimate\ConvertEstimateController;
use Crater\Http\Controllers\V1\Estimate\EstimatesController;
use Crater\Http\Controllers\V1\Estimate\EstimateTemplatesController;
use Crater\Http\Controllers\V1\Estimate\SendEstimateController;
use Crater\Http\Controllers\V1\Expense\ExpenseCategoriesController;
use Crater\Http\Controllers\V1\Expense\ExpensesController;
use Crater\Http\Controllers\V1\Expense\ShowReceiptController;
use Crater\Http\Controllers\V1\Expense\UploadReceiptController;
use Crater\Http\Controllers\V1\Family\FamilyMembersController;
use Crater\Http\Controllers\V1\Staff\StaffMembersController;
use Crater\Http\Controllers\V1\Staff\PayrollController;
use Crater\Http\Controllers\V1\General\BootstrapController;
use Crater\Http\Controllers\V1\General\CountriesController;
use Crater\Http\Controllers\V1\General\CurrenciesController;
use Crater\Http\Controllers\V1\General\DateFormatsController;
use Crater\Http\Controllers\V1\General\FiscalYearsController;
use Crater\Http\Controllers\V1\General\LanguagesController;
use Crater\Http\Controllers\V1\General\NextNumberController;
use Crater\Http\Controllers\V1\General\NotesController;
use Crater\Http\Controllers\V1\General\SearchController;
use Crater\Http\Controllers\V1\General\TimezonesController;
use Crater\Http\Controllers\V1\Invoice\ChangeInvoiceStatusController;
use Crater\Http\Controllers\V1\Invoice\CloneInvoiceController;
use Crater\Http\Controllers\V1\Invoice\InvoicesController;
use Crater\Http\Controllers\V1\Invoice\InvoiceTemplatesController;
use Crater\Http\Controllers\V1\Invoice\SendInvoiceController;
use Crater\Http\Controllers\V1\Item\ItemsController;
use Crater\Http\Controllers\V1\Item\UnitsController;
use Crater\Http\Controllers\V1\Mobile\AuthController;
use Crater\Http\Controllers\V1\Onboarding\AppDomainController;
use Crater\Http\Controllers\V1\Onboarding\DatabaseConfigurationController;
use Crater\Http\Controllers\V1\Onboarding\FinishController;
use Crater\Http\Controllers\V1\Onboarding\LoginController;
use Crater\Http\Controllers\V1\Onboarding\OnboardingWizardController;
use Crater\Http\Controllers\V1\Onboarding\PermissionsController;
use Crater\Http\Controllers\V1\Onboarding\RequirementsController;
use Crater\Http\Controllers\V1\Payment\PaymentMethodsController;
use Crater\Http\Controllers\V1\Payment\PaymentsController;
use Crater\Http\Controllers\V1\Payment\SendPaymentController;
use Crater\Http\Controllers\V1\Settings\CompanyController;
use Crater\Http\Controllers\V1\Settings\DiskController;
use Crater\Http\Controllers\V1\Settings\GetCompanySettingsController;
use Crater\Http\Controllers\V1\Settings\GetUserSettingsController;
use Crater\Http\Controllers\V1\Settings\MailConfigurationController;
use Crater\Http\Controllers\V1\Settings\SchoolLevelsController;
use Crater\Http\Controllers\V1\Settings\TaxTypesController;
use Crater\Http\Controllers\V1\Settings\UpdateCompanySettingsController;
use Crater\Http\Controllers\V1\Settings\UpdateUserSettingsController;
use Crater\Http\Controllers\V1\Student\StudentsController;
use Crater\Http\Controllers\V1\Student\StudentRelocationController;
use Crater\Http\Controllers\V1\Update\CheckVersionController;
use Crater\Http\Controllers\V1\Update\CopyFilesController;
use Crater\Http\Controllers\V1\Update\DeleteFilesController;
use Crater\Http\Controllers\V1\Update\DownloadUpdateController;
use Crater\Http\Controllers\V1\Update\FinishUpdateController;
use Crater\Http\Controllers\V1\Update\MigrateUpdateController;
use Crater\Http\Controllers\V1\Update\UnzipUpdateController;
use Crater\Http\Controllers\V1\Users\UsersController;
use Crater\Http\Controllers\V1\Academic\AcademicYearsController;
use Crater\Http\Controllers\V1\Audit\AuditLogsController;
use Crater\Http\Controllers\V1\Academic\DivisionsController;
use Crater\Http\Controllers\V1\Academic\EnrollmentsController;
use Crater\Http\Controllers\V1\Academic\GradeLevelsController;
use Crater\Http\Controllers\V1\Academic\SubjectsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


// ping
//----------------------------------

Route::get('ping', function () {
    return response()->json([
        'success' => 'crater-self-hosted',
    ]);
})->name('ping');


// Version 1 endpoints
// --------------------------------------
Route::prefix('/v1')->group(function () {


    // App version
    // ----------------------------------

    Route::get('/app/version', AppVersionController::class);


    // Authentication & Password Reset
    //----------------------------------

    Route::group(['prefix' => 'auth'], function () {
        Route::post('login', [AuthController::class, 'login']);

        Route::post('logout', [AuthController::class, 'logout'])->middleware(['auth:sanctum', 'active-account']);

        // Send reset password mail
        Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware("throttle:10,2");

        // handle reset password form process
        Route::post('reset/password', [ResetPasswordController::class, 'reset']);
    });


    // Countries
    //----------------------------------

    Route::get('/countries', CountriesController::class);


    // Onboarding
    //----------------------------------

    Route::middleware(['redirect-if-installed'])->group(function () {
        Route::get('/onboarding/wizard-step', [OnboardingWizardController::class, 'getStep']);

        Route::post('/onboarding/wizard-step', [OnboardingWizardController::class, 'updateStep']);

        Route::get('/onboarding/requirements', [RequirementsController::class, 'requirements']);

        Route::get('/onboarding/permissions', [PermissionsController::class, 'permissions']);

        Route::post('/onboarding/database/config', [DatabaseConfigurationController::class, 'saveDatabaseEnvironment']);

        Route::get('/onboarding/database/config', [DatabaseConfigurationController::class, 'getDatabaseEnvironment']);

        Route::put('/onboarding/set-domain', AppDomainController::class);

        Route::post('/onboarding/login', LoginController::class);

        Route::post('/onboarding/finish', FinishController::class);
    });


        /*
    |--------------------------------------------------------------------------
    | Suite institucional — estructura academica
    |--------------------------------------------------------------------------
    |
    | Rutas del esquema NUEVO de autorizacion. Igual que el bloque heredado cerrado por R1,
    | de mas abajo, aca cada ruta declara el permiso que exige y el tenant se
    | valida contra el usuario autenticado, no contra el header que mande el
    | cliente.
    |
    | Mientras convivan los dos esquemas, todo lo academico va aca y lo
    | economico exige tenant y permisos en el bloque de mas abajo.
    |
    */

    Route::middleware(['auth:sanctum', 'active-account', 'tenant', 'tenant-resource'])->group(function () {
        // --- Personal ---
        Route::get('/staff', [StaffMembersController::class, 'index'])
            ->middleware('permission:hr.staff.view');
        Route::post('/staff', [StaffMembersController::class, 'store'])
            ->middleware('permission:hr.staff.manage');
        Route::put('/staff/{staffMember}', [StaffMembersController::class, 'update'])
            ->middleware('permission:hr.staff.manage');
        Route::post('/staff/{staffMember}/assignments', [StaffMembersController::class, 'storeAssignment'])
            ->middleware('permission:hr.staff.manage');
        Route::put('/staff/{staffMember}/assignments/{staffAssignment}', [StaffMembersController::class, 'updateAssignment'])
            ->middleware('permission:hr.staff.manage');

        Route::get('/payroll', [PayrollController::class, 'index'])->middleware('permission:hr.payroll.view');
        Route::post('/payroll/periods', [PayrollController::class, 'storePeriod'])->middleware('permission:hr.payroll.manage');
        Route::post('/payroll/periods/{payrollPeriod}/slips', [PayrollController::class, 'storeSlip'])->middleware('permission:hr.payroll.manage');
        Route::post('/payroll/slips/{payrollSlip}/approve', [PayrollController::class, 'approveSlip'])->middleware('permission:hr.payroll.approve');
        Route::post('/payroll/slips/{payrollSlip}/payments', [PayrollController::class, 'storePayment'])->middleware('permission:hr.payroll.pay');
        Route::post('/payroll/payments/{payrollPayment}/reverse', [PayrollController::class, 'reversePayment'])->middleware('permission:hr.payroll.pay');

        Route::get('/academic-years', [AcademicYearsController::class, 'index'])
            ->middleware('permission:academic.year.view');

        Route::get('/academic-years/{academicYear}', [AcademicYearsController::class, 'show'])
            ->middleware('permission:academic.year.view');

        Route::post('/academic-years', [AcademicYearsController::class, 'store'])
            ->middleware('permission:academic.year.manage');

        Route::put('/academic-years/{academicYear}', [AcademicYearsController::class, 'update'])
            ->middleware('permission:academic.year.manage');

        // --- cursos ---
        Route::get('/grade-levels', [GradeLevelsController::class, 'index'])
            ->middleware('permission:academic.division.view');

        Route::post('/grade-levels', [GradeLevelsController::class, 'store'])
            ->middleware('permission:academic.division.manage');

        Route::put('/grade-levels/{gradeLevel}', [GradeLevelsController::class, 'update'])
            ->middleware('permission:academic.division.manage');

        // --- divisiones ---
        Route::get('/divisions', [DivisionsController::class, 'index'])
            ->middleware('permission:academic.division.view');

        Route::get('/divisions/{division}', [DivisionsController::class, 'show'])
            ->middleware('permission:academic.division.view');

        Route::post('/divisions', [DivisionsController::class, 'store'])
            ->middleware('permission:academic.division.manage');

        Route::put('/divisions/{division}', [DivisionsController::class, 'update'])
            ->middleware('permission:academic.division.manage');

        // --- materias (espacios curriculares) ---
        // Se autorizan con los permisos de plan de estudios: cambiar una
        // materia es cambiar el disenio curricular.
        Route::get('/study-plans', [SubjectsController::class, 'studyPlans'])
            ->middleware('permission:academic.study_plan.view');

        Route::get('/subjects', [SubjectsController::class, 'index'])
            ->middleware('permission:academic.study_plan.view');

        Route::post('/subjects', [SubjectsController::class, 'store'])
            ->middleware('permission:academic.study_plan.manage');

        Route::put('/subjects/{subject}', [SubjectsController::class, 'update'])
            ->middleware('permission:academic.study_plan.manage');

        Route::delete('/subjects/{subject}', [SubjectsController::class, 'destroy'])
            ->middleware('permission:academic.study_plan.manage');

        // --- matriculas ---
        Route::get('/enrollments', [EnrollmentsController::class, 'index'])
            ->middleware('permission:academic.enrollment.view');

        Route::get('/enrollments/disponibles', [EnrollmentsController::class, 'disponibles'])
            ->middleware('permission:academic.enrollment.view');

        Route::post('/enrollments', [EnrollmentsController::class, 'store'])
            ->middleware('permission:academic.enrollment.manage');

        Route::put('/enrollments/{enrollment}', [EnrollmentsController::class, 'update'])
            ->middleware('permission:academic.enrollment.manage,academic.enrollment.transfer');

        Route::get('/audit-logs', [AuditLogsController::class, 'index'])
            ->middleware('permission:system.audit.view');

        Route::get('/data-reconciliation', [DataReconciliationController::class, 'index'])
            ->middleware('permission:data.reconcile');
        Route::post('/data-reconciliation/preview', [DataReconciliationController::class, 'preview'])
            ->middleware('permission:data.reconcile');
        Route::post('/data-reconciliation/apply', [DataReconciliationController::class, 'apply'])
            ->middleware('permission:data.reconcile');

    });

    // Excepciones deliberadas de tenant: el perfil propio y bootstrap no
    // dependen de elegir nivel; el selector debe funcionar antes de elegirlo.
    // Los cinco catalogos son constantes, sin datos institucionales ni secretos.
    // Allowlist exacta y documentada en RouteAuthorizationMatrixTest.
    Route::middleware(['auth:sanctum', 'active-account'])->group(function () {
        Route::get('/bootstrap', BootstrapController::class);
        Route::get('/auth/check', [AuthController::class, 'check']);
        Route::get('/currencies', CurrenciesController::class);
        Route::get('/timezones', TimezonesController::class);
        Route::get('/date/formats', DateFormatsController::class);
        Route::get('/fiscal/years', FiscalYearsController::class);
        Route::get('/languages', LanguagesController::class);
        Route::get('/me', [CompanyController::class, 'getUser']);
        Route::put('/me', [CompanyController::class, 'updateProfile']);
        Route::get('/me/settings', GetUserSettingsController::class);
        Route::put('/me/settings', UpdateUserSettingsController::class);
        Route::post('/me/upload-avatar', [CompanyController::class, 'uploadAvatar']);
        Route::get('/school-levels', [SchoolLevelsController::class, 'index']);
    });

    Route::middleware(['auth:sanctum', 'active-account', 'admin', 'tenant', 'tenant-resource'])->group(function () {


        // Bootstrap
        //----------------------------------



        // Dashboard
        //----------------------------------

        Route::get('/dashboard', DashboardController::class)->middleware('permission:finance.view');


        // Auth check
        //----------------------------------



        // Search users
        //----------------------------------

        Route::get('/search', SearchController::class)->middleware('permission:finance.view,system.user.view');


        // MISC
        //----------------------------------






        Route::get('/next-number', NextNumberController::class)->middleware('permission:finance.view');


        // Self Update
        //----------------------------------

        Route::get('/check/update', CheckVersionController::class)->middleware('permission:system.settings.manage');

        Route::post('/update/download', DownloadUpdateController::class)->middleware('permission:system.settings.manage');

        Route::post('/update/unzip', UnzipUpdateController::class)->middleware('permission:system.settings.manage');

        Route::post('/update/copy', CopyFilesController::class)->middleware('permission:system.settings.manage');

        Route::post('/update/delete', DeleteFilesController::class)->middleware('permission:system.settings.manage');

        Route::post('/update/migrate', MigrateUpdateController::class)->middleware('permission:system.settings.manage');

        Route::post('/update/finish', FinishUpdateController::class)->middleware('permission:system.settings.manage');


        // Customers
        //----------------------------------

        // Hasta la baja logica (fila 5), borrar una familia institucional puede
        // afectar hermanos/documentos de otros niveles: solo total admin.
        Route::post('/customers/delete', [CustomersController::class, 'delete'])
            ->middleware(['permission:finance.invoice.manage', 'permission:system.settings.manage']);

        Route::get('customers/{customer}/stats', CustomerStatsController::class)->middleware('permission:finance.view');

        Route::apiResource('customers', CustomersController::class)->only(['index', 'show'])
            ->middleware('permission:finance.view');
        Route::apiResource('customers', CustomersController::class)->only(['store', 'update'])
            ->middleware('permission:finance.invoice.manage');


        // Families / responsible adults (canonical family_members)
        //----------------------------------

        Route::get('/family-members', [FamilyMembersController::class, 'index'])->middleware('permission:students.view_file,finance.view');
        Route::post('/family-members', [FamilyMembersController::class, 'store'])->middleware('permission:students.guardian.manage');
        Route::put('/family-members/{familyMember}', [FamilyMembersController::class, 'update'])->middleware('permission:students.guardian.manage');


        // Students / school records
        //----------------------------------

        Route::get('/students/placement-options', [StudentsController::class, 'placementOptions'])->middleware('permission:students.manage');
        Route::get('/students/{student}/relocation-options', [StudentRelocationController::class, 'options'])->middleware('permission:academic.enrollment.transfer');
        Route::put('/students/{student}/relocate', [StudentRelocationController::class, 'relocate'])->middleware('permission:academic.enrollment.transfer');
        Route::apiResource('students', StudentsController::class)->only(['index', 'show'])
            ->middleware('permission:students.view_basic');
        Route::apiResource('students', StudentsController::class)->only(['store', 'update', 'destroy'])
            ->middleware('permission:students.manage');


        // Items
        //----------------------------------

        Route::post('/items/delete', [ItemsController::class, 'delete'])->middleware('permission:finance.invoice.manage');

        Route::apiResource('items', ItemsController::class)->only(['index', 'show'])
            ->middleware('permission:finance.view');
        Route::apiResource('items', ItemsController::class)->only(['store', 'update'])
            ->middleware('permission:finance.invoice.manage');

        Route::apiResource('units', UnitsController::class)->only(['index', 'show'])
            ->middleware('permission:finance.view');
        Route::apiResource('units', UnitsController::class)->only(['store', 'update', 'destroy'])
            ->middleware('permission:finance.invoice.manage');


        // School billing options
        //----------------------------------
        Route::get('/school-billing/options', SchoolBillingOptionsController::class)->middleware('tenant')->middleware('permission:finance.view');

        // Invoices
        //-------------------------------------------------

        Route::post('/invoices/{invoice}/send', SendInvoiceController::class)->middleware('permission:finance.invoice.manage');

        Route::post('/invoices/{invoice}/clone', CloneInvoiceController::class)->middleware('permission:finance.invoice.manage');

        Route::post('/invoices/{invoice}/status', ChangeInvoiceStatusController::class)->middleware('permission:finance.invoice.manage');

        Route::post('/invoices/delete', [InvoicesController::class, 'delete'])->middleware('permission:finance.invoice.manage');

        Route::get('/invoices/templates', InvoiceTemplatesController::class)->middleware('permission:finance.view');

        Route::apiResource('invoices', InvoicesController::class)->only(['index', 'show'])
            ->middleware('permission:finance.view');
        Route::apiResource('invoices', InvoicesController::class)->only(['store', 'update'])
            ->middleware('permission:finance.invoice.manage');


        // Estimates
        //-------------------------------------------------

        Route::post('/estimates/{estimate}/send', SendEstimateController::class)->middleware('permission:finance.invoice.manage');

        Route::post('/estimates/{estimate}/status', ChangeEstimateStatusController::class)->middleware('permission:finance.invoice.manage');

        Route::post('/estimates/{estimate}/convert-to-invoice', ConvertEstimateController::class)->middleware('permission:finance.invoice.manage');

        Route::get('/estimates/templates', EstimateTemplatesController::class)->middleware('permission:finance.view');

        Route::post('/estimates/delete', [EstimatesController::class, 'delete'])->middleware('permission:finance.invoice.manage');

        Route::apiResource('estimates', EstimatesController::class)->only(['index', 'show'])
            ->middleware('permission:finance.view');
        Route::apiResource('estimates', EstimatesController::class)->only(['store', 'update'])
            ->middleware('permission:finance.invoice.manage');


        // Expenses
        //----------------------------------

        Route::get('/expenses/{expense}/show/receipt', ShowReceiptController::class)->middleware('permission:finance.view');

        Route::post('/expenses/{expense}/upload/receipts', UploadReceiptController::class)->middleware('permission:finance.expense.manage');

        Route::post('/expenses/delete', [ExpensesController::class, 'delete'])->middleware('permission:finance.expense.manage');

        Route::apiResource('expenses', ExpensesController::class)->only(['index', 'show'])
            ->middleware('permission:finance.view');
        Route::apiResource('expenses', ExpensesController::class)->only(['store', 'update'])
            ->middleware('permission:finance.expense.manage');

        Route::apiResource('categories', ExpenseCategoriesController::class)->only(['index', 'show'])
            ->middleware('permission:finance.view');
        Route::apiResource('categories', ExpenseCategoriesController::class)->only(['store', 'update', 'destroy'])
            ->middleware('permission:finance.expense.manage');


        // Payments
        //----------------------------------

        Route::post('/payments/{payment}/send', SendPaymentController::class)->middleware('permission:finance.payment.manage');

        Route::post('/payments/delete', [PaymentsController::class, 'delete'])->middleware('permission:finance.payment.manage');

        Route::apiResource('payments', PaymentsController::class)->only(['index', 'show'])
            ->middleware('permission:finance.view');
        Route::apiResource('payments', PaymentsController::class)->only(['store', 'update'])
            ->middleware('permission:finance.payment.manage');

        Route::apiResource('payment-methods', PaymentMethodsController::class)->only(['index', 'show'])
            ->middleware('permission:system.settings.manage');
        Route::apiResource('payment-methods', PaymentMethodsController::class)->only(['store', 'update', 'destroy'])
            ->middleware('permission:system.settings.manage');


        // Custom fields
        //----------------------------------

        Route::apiResource('custom-fields', CustomFieldsController::class)->only(['index', 'show'])
            ->middleware('permission:system.settings.manage');
        Route::apiResource('custom-fields', CustomFieldsController::class)->only(['store', 'update', 'destroy'])
            ->middleware('permission:system.settings.manage');


        // Backup & Disk
        //----------------------------------

        Route::apiResource('backups', BackupsController::class)->only(['index'])
            ->middleware('permission:system.backup.manage');
        Route::apiResource('backups', BackupsController::class)->only(['store', 'destroy'])
            ->middleware('permission:system.backup.manage');

        Route::apiResource('/disks', DiskController::class)->only(['index', 'show'])
            ->middleware('permission:system.settings.manage');
        Route::apiResource('/disks', DiskController::class)->only(['store', 'update', 'destroy'])
            ->middleware('permission:system.settings.manage');

        Route::get('download-backup', DownloadBackupController::class)->middleware('permission:system.backup.manage');

        Route::get('/disk/drivers', [DiskController::class, 'getDiskDrivers'])->middleware('permission:system.settings.manage');


        // Settings
        //----------------------------------







        Route::put('/company', [CompanyController::class, 'updateCompany'])->middleware('permission:system.settings.manage');

        Route::post('/company/upload-logo', [CompanyController::class, 'uploadCompanyLogo'])->middleware('permission:system.settings.manage');

        Route::get('/company/settings', GetCompanySettingsController::class)->middleware('permission:system.settings.manage');

        Route::post('/company/settings', UpdateCompanySettingsController::class)->middleware('permission:system.settings.manage');


        Route::put('/school-levels/{schoolLevel}', [SchoolLevelsController::class, 'update'])->middleware('permission:system.school_level.manage');


        // Mails
        //----------------------------------

        Route::get('/mail/drivers', [MailConfigurationController::class, 'getMailDrivers'])->middleware('permission:system.settings.manage');

        Route::get('/mail/config', [MailConfigurationController::class, 'getMailEnvironment'])->middleware('permission:system.settings.manage');

        Route::post('/mail/config', [MailConfigurationController::class, 'saveMailEnvironment'])->middleware('permission:system.settings.manage');

        Route::post('/mail/test', [MailConfigurationController::class, 'testEmailConfig'])->middleware('permission:system.settings.manage');


        Route::apiResource('notes', NotesController::class)->only(['index', 'show'])
            ->middleware('permission:system.settings.manage');
        Route::apiResource('notes', NotesController::class)->only(['store', 'update', 'destroy'])
            ->middleware('permission:system.settings.manage');


        // Tax Types
        //----------------------------------

        Route::apiResource('tax-types', TaxTypesController::class)->only(['index', 'show'])
            ->middleware('permission:system.settings.manage');
        Route::apiResource('tax-types', TaxTypesController::class)->only(['store', 'update', 'destroy'])
            ->middleware('permission:system.settings.manage');


        // Users
        //----------------------------------

        Route::get('/roles', [\Crater\Http\Controllers\V1\Users\RoleAssignmentsController::class, 'roles'])->middleware('permission:system.user.view');
        Route::get('/users/{user}/role-assignments', [\Crater\Http\Controllers\V1\Users\RoleAssignmentsController::class, 'index'])->middleware('permission:system.user.view');
        Route::post('/users/{user}/role-assignments', [\Crater\Http\Controllers\V1\Users\RoleAssignmentsController::class, 'store'])->middleware('permission:system.role.assign');
        Route::post('/role-assignments/{id}/revoke', [\Crater\Http\Controllers\V1\Users\RoleAssignmentsController::class, 'revoke'])->middleware('permission:system.role.assign');
        Route::get('/users/{user}/effective-permissions', [\Crater\Http\Controllers\V1\Users\RoleAssignmentsController::class, 'effective'])->middleware('permission:system.user.view');

        Route::post('/users/delete', [UsersController::class, 'delete'])->middleware('permission:system.user.manage');

        Route::apiResource('/users', UsersController::class)->only(['index', 'show'])
            ->middleware('permission:system.user.view');
        Route::apiResource('/users', UsersController::class)->only(['store', 'update'])
            ->middleware('permission:system.user.manage');
    });
});
