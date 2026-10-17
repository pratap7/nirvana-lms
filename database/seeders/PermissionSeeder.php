<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Complete granular permission catalog matching the architecture blueprint.
     */
    public const array PERMISSIONS = [
        // Identity
        'user.list',
        'user.view',
        'user.create',
        'user.update',
        'user.delete',
        'user.suspend',
        'user.impersonate',
        'role.assign',
        'role.revoke',
        'branch.manage',

        // Catalog
        'program.list',
        'program.manage',
        'course.list',
        'course.view',
        'course.create',
        'course.update',
        'course.delete',
        'course.publish',
        'course.approve',
        'lesson.manage',
        'content.upload',

        // Scheduling
        'batch.list',
        'batch.view',
        'batch.create',
        'batch.update',
        'batch.delete',
        'session.manage',
        'attendance.view',
        'attendance.mark',
        'attendance.edit',
        'holiday.manage',

        // Enrollment
        'enrollment.list',
        'enrollment.view',
        'enrollment.create',
        'enrollment.cancel',
        'enrollment.transfer',
        'enrollment.hold',

        // Billing
        'payment.list',
        'payment.view',
        'payment.verify',
        'payment.refund',
        'fee.manage',
        'discount.manage',
        'invoice.generate',
        'invoice.view',

        // Learning
        'progress.view_own',
        'progress.view_any',
        'progress.manage',

        // Assessment
        'quiz.create',
        'quiz.update',
        'quiz.delete',
        'quiz.attempt',
        'assignment.create',
        'assignment.grade',
        'submission.view',

        // Certification
        'certificate.issue',
        'certificate.revoke',
        'certificate.view_any',
        'template.manage',

        // Events
        'workshop.list',
        'workshop.create',
        'workshop.update',
        'workshop.delete',
        'workshop.register',
        'registration.manage',

        // Communication
        'announcement.create',
        'announcement.manage',
        'notification.send',
        'template.manage',

        // Reporting
        'report.attendance',
        'report.revenue',
        'report.enrollment',
        'report.progress',
        'report.export',

        // Wellbeing
        'checkin.record',
        'checkin.view_own',
        'checkin.view_any',
        'journal.manage_own',
        'prakriti.assess',
        'prakriti.view',
        'counseling.book',
        'counseling.conduct',
        'counseling.notes.view',
        'counseling.notes.create',
        'diet_log.manage_own',
        'routine_log.manage_own',
        'wellbeing.view_any',
    ];

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create permissions
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // 2. Create Roles
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $moderatorRole = Role::firstOrCreate(['name' => 'moderator', 'guard_name' => 'web']);
        $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $guestTeacherRole = Role::firstOrCreate(['name' => 'guest_teacher', 'guard_name' => 'web']);
        $studentRole = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        // Admin gets all permissions
        $adminRole->syncPermissions(Permission::all());

        // Moderator permissions
        $moderatorRole->syncPermissions([
            'user.view',
            'course.list',
            'course.view',
            'course.publish',
            'lesson.manage',
            'batch.list',
            'batch.view',
            'enrollment.list',
            'enrollment.view',
            'payment.list',
            'payment.view',
            'payment.verify',
            'invoice.view',
            'progress.view_any',
            'workshop.list',
            'announcement.create',
            'announcement.manage',
            'report.attendance',
            'report.enrollment',
        ]);

        // Teacher permissions
        $teacherRole->syncPermissions([
            'course.list',
            'course.view',
            'course.create',
            'course.update',
            'lesson.manage',
            'content.upload',
            'batch.list',
            'batch.view',
            'session.manage',
            'attendance.view',
            'attendance.mark',
            'attendance.edit',
            'quiz.create',
            'quiz.update',
            'assignment.create',
            'assignment.grade',
            'submission.view',
            'progress.view_any',
            'counseling.conduct',
            'counseling.notes.create',
        ]);

        // Guest Teacher permissions (inherits teacher permissions, scoped at assignment level)
        $guestTeacherRole->syncPermissions([
            'course.view',
            'batch.view',
            'session.manage',
            'attendance.view',
            'attendance.mark',
            'assignment.grade',
            'submission.view',
            'progress.view_any',
        ]);

        // Student permissions
        $studentRole->syncPermissions([
            'course.list',
            'course.view',
            'progress.view_own',
            'quiz.attempt',
            'workshop.list',
            'workshop.register',
            'checkin.record',
            'checkin.view_own',
            'journal.manage_own',
            'counseling.book',
            'diet_log.manage_own',
            'routine_log.manage_own',
            'prakriti.view',
        ]);
    }
}
