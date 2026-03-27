<?php

use App\Models\Company;
use App\Services\CompanyDeletionService;

Route::get('/debug/companies', function() {
    $companies = Company::whereIn('ruc', ['1234567890001', '1234567890002', '1234567890003'])->get();
    
    foreach($companies as $c) {
        echo "
        {$c->razon_social}:
        - last_activity: {$c->last_activity_at}
        - warning_sent: {$c->deletion_warning_sent_at}
        - final_sent: {$c->deletion_final_notice_sent_at}
        - is_marked: {$c->is_marked_for_deletion}
        ";
    }
    
    echo "\n\nCompanies needing warning:\n";
    $service = new CompanyDeletionService();
    $warning = $service->getCompaniesNeedingDeletionWarning(1);
    dd($warning);
});
