<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class ReleaseFunctionalAudit extends Command
{
    protected $signature='release:functional-audit';
    protected $description='Audit critical C-Net Library admin workflows for route completeness.';
    public function handle():int
    {
        $modules=[
            'Public & Login'=>['home','login','admission.create','admission.store','enquiry.create','enquiry.store','digital-library.index','jobs.index'],
            'Dashboard & Reports'=>['admin.dashboard','admin.reports.index','admin.settings.index','admin.security.index'],
            'Branches'=>['admin.branches.index','admin.branches.store','admin.branches.update','admin.branches.destroy'],
            'Students'=>['admin.students.index','admin.students.store','admin.students.show','admin.students.update','admin.students.destroy','admin.students.force-destroy','admin.students.id-card','admin.students.id-cards.bulk'],
            'Hall & Seats'=>['admin.study-space.index','admin.study-space.seat-tools','admin.study-space.halls.store','admin.study-space.halls.update','admin.study-space.halls.destroy','admin.study-space.seats.store','admin.study-space.seats.bulk','admin.study-space.seats.update','admin.study-space.seats.destroy','admin.seats.available'],
            'Slots & Fee Plans'=>['admin.slots.index','admin.study-space.slots.store','admin.study-space.slots.update','admin.study-space.slots.destroy','admin.study-space.plans.store','admin.study-space.plans.update'],
            'Seat Allocation'=>['admin.study-space.allocations.store','admin.study-space.allocations.update'],
            'Lockers'=>['admin.lockers.index','admin.lockers.store','admin.lockers.bulk','admin.lockers.update','admin.lockers.destroy','admin.lockers.allocations.store','admin.lockers.allocations.update','admin.lockers.allocations.payments.store'],
            'Admissions & Enquiries'=>['admin.admissions.index','admin.admissions.show','admin.admissions.approve','admin.enquiries.index','admin.enquiries.update','admin.enquiries.convert'],
            'Payments & Attendance'=>['admin.expenses.index','admin.expenses.store','admin.students.payments.store','admin.payments.receipt','admin.attendance.index','admin.attendance.scan','admin.attendance.check-in','admin.attendance.check-out'],
            'Physical Library'=>['admin.library.index','admin.library.issue','admin.library.reservations.store','admin.library.reservations.cancel','admin.library.return','admin.library.lost','admin.library.charges.store','admin.library.recover'],
            'Digital Library'=>['admin.digital-resources.index','admin.digital-resources.store','admin.digital-resources.update','admin.digital-resources.destroy'],
            'Staff CRUD'=>['admin.staff.index','admin.staff.store','admin.staff.update','admin.staff.destroy','admin.staff.attendance','admin.staff.leaves.update','admin.staff.payroll'],
            'Jobs CRUD'=>['admin.jobs.index','admin.jobs.store','admin.jobs.update','admin.jobs.destroy'],
            'Communications CRUD'=>['admin.communications.index','admin.communications.templates.store','admin.communications.templates.update','admin.communications.templates.destroy'],
            'CMS Management'=>['admin.cms.index','admin.cms.pages.update','admin.cms.faqs.store','admin.cms.faqs.update','admin.cms.faqs.destroy','admin.cms.testimonials.store','admin.cms.testimonials.update','admin.cms.testimonials.destroy','admin.cms.gallery.store','admin.cms.gallery.destroy'],
            'Mobile Student API'=>['api.mobile.v1.login','api.mobile.v1.dashboard','api.mobile.v1.profile','api.mobile.v1.membership','api.mobile.v1.payments','api.mobile.v1.attendance','api.mobile.v1.seat-allocation','api.mobile.v1.books','api.mobile.v1.issued-books','api.mobile.v1.digital-resources','api.mobile.v1.jobs','api.mobile.v1.qr-member-id','api.mobile.v1.support'],
            'Mobile Admin API'=>['api.mobile.v1.admin.login','api.mobile.v1.admin.logout','api.mobile.v1.admin.dashboard','api.mobile.v1.admin.students','api.mobile.v1.admin.enquiries','api.mobile.v1.admin.payments','api.mobile.v1.admin.attendance','api.mobile.v1.admin.books','api.mobile.v1.admin.book-issues','api.mobile.v1.admin.lockers'],
        ];
        $failures=[];foreach($modules as $module=>$routes){$missing=array_values(array_filter($routes,fn($name)=>!Route::has($name)));if($missing===[])$this->info("PASS  {$module}");else{$this->error("FAIL  {$module}");foreach($missing as $name){$this->line("  MISSING: {$name}");$failures[]="{$module}: {$name}";}}}
        $this->newLine();if($failures!==[]){$this->error('FUNCTIONAL AUDIT FAILED — do not call the release final.');return self::FAILURE;}$this->info('FUNCTIONAL AUDIT PASSED — critical workflows and management CRUD routes are registered.');return self::SUCCESS;
    }
}
