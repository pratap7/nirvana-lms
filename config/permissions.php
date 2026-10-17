<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Permission Catalog Grouped by Domain Module
    |--------------------------------------------------------------------------
    |
    | Single source of truth for all granular permissions in the system.
    | Grouped by domain for intuitive role management in the Admin UI.
    |
    */
    'modules' => [
        'Identity' => [
            'user.list' => 'List and search users',
            'user.view' => 'View user profiles',
            'user.create' => 'Create new user accounts',
            'user.update' => 'Edit user profile information',
            'user.delete' => 'Delete user accounts',
            'user.suspend' => 'Deactivate or suspend user accounts',
            'user.impersonate' => 'Impersonate users for support',
            'role.manage' => 'Create, edit and configure roles and permissions',
            'role.assign' => 'Assign roles to users',
            'role.revoke' => 'Revoke roles from users',
            'branch.manage' => 'Create and configure institute branches',
            'audit.view' => 'View system activity and audit logs',
            'audit.export' => 'Export system audit logs to CSV',
            'student.health.view' => 'View sensitive student health declarations and emergency contacts',
            'teacher.invite' => 'Invite guest teachers with scoped access',
        ],

        'Catalog' => [
            'program.list' => 'View programs list',
            'program.manage' => 'Create, update, and delete programs',
            'course.list' => 'View courses catalog',
            'course.view' => 'View course details and curriculum',
            'course.create' => 'Create new course drafts',
            'course.update' => 'Edit course details and syllabus',
            'course.delete' => 'Delete course records',
            'course.publish' => 'Publish courses to make them publicly visible',
            'course.approve' => 'Approve courses submitted for review',
            'lesson.manage' => 'Add and edit lessons and modules',
            'content.upload' => 'Upload video and documents to lessons',
        ],

        'Scheduling' => [
            'batch.list' => 'View batches',
            'batch.view' => 'View batch schedules and rosters',
            'batch.create' => 'Create new batches',
            'batch.update' => 'Edit batch details and schedules',
            'batch.delete' => 'Delete batches',
            'session.manage' => 'Schedule and manage class sessions',
            'attendance.view' => 'View attendance records',
            'attendance.mark' => 'Mark session attendance',
            'attendance.edit' => 'Update previously marked attendance',
            'holiday.manage' => 'Configure branch and national holidays',
        ],

        'Enrollment' => [
            'enrollment.list' => 'List student enrollments',
            'enrollment.view' => 'View enrollment details',
            'enrollment.create' => 'Enroll students into batches',
            'enrollment.cancel' => 'Cancel enrollments',
            'enrollment.transfer' => 'Transfer students between batches',
            'enrollment.hold' => 'Put enrollments on hold',
        ],

        'Billing' => [
            'payment.list' => 'List payment transactions',
            'payment.view' => 'View transaction details and gateway payloads',
            'payment.verify' => 'Manually verify offline or pending payments',
            'payment.refund' => 'Process payment refunds',
            'fee.manage' => 'Configure fee structures and pricing',
            'discount.manage' => 'Create and manage discount codes',
            'invoice.generate' => 'Generate and send invoices',
            'invoice.view' => 'View and download invoices',
        ],

        'Learning' => [
            'progress.view_own' => 'View own learning progress',
            'progress.view_any' => 'View learning progress of any student',
            'progress.manage' => 'Manually update progress records',
        ],

        'Assessment' => [
            'quiz.create' => 'Create and configure quizzes',
            'quiz.update' => 'Edit quiz questions and settings',
            'quiz.delete' => 'Delete quizzes',
            'quiz.attempt' => 'Attempt quizzes',
            'assignment.create' => 'Create course assignments',
            'assignment.grade' => 'Grade student assignments and submissions',
            'submission.view' => 'View submitted assignments',
        ],

        'Certification' => [
            'certificate.issue' => 'Issue completion certificates',
            'certificate.revoke' => 'Revoke issued certificates',
            'certificate.view_any' => 'View all issued certificates',
            'template.manage' => 'Manage certificate HTML/PDF templates',
        ],

        'Events' => [
            'workshop.list' => 'List workshops and retreats',
            'workshop.create' => 'Create new workshops',
            'workshop.update' => 'Edit workshops',
            'workshop.delete' => 'Delete workshops',
            'workshop.register' => 'Register for workshops',
            'registration.manage' => 'Manage workshop registrations and attendees',
        ],

        'Communication' => [
            'announcement.create' => 'Create global or scoped announcements',
            'announcement.manage' => 'Edit and delete announcements',
            'notification.send' => 'Send broadcast emails and WhatsApp notifications',
            'template.manage' => 'Manage notification templates',
        ],

        'Reporting' => [
            'report.attendance' => 'Generate attendance reports',
            'report.revenue' => 'Generate revenue and billing reports',
            'report.enrollment' => 'Generate enrollment analytics',
            'report.progress' => 'Generate student progress reports',
            'report.export' => 'Export reports to Excel and PDF',
        ],

        'Wellbeing' => [
            'checkin.record' => 'Record daily mood and wellness check-in',
            'checkin.view_own' => 'View own wellness history',
            'checkin.view_any' => 'View aggregated wellness reports',
            'journal.manage_own' => 'Manage private reflective journal entries',
            'prakriti.assess' => 'Conduct Ayurvedic Prakriti assessment',
            'prakriti.view' => 'View Prakriti (Dosha) profile',
            'counseling.book' => 'Book counseling or mentoring session',
            'counseling.conduct' => 'Conduct counseling sessions',
            'counseling.notes.view' => 'View confidential counseling notes',
            'counseling.notes.create' => 'Create confidential counseling notes',
            'diet_log.manage_own' => 'Track daily sattvic diet logs',
            'routine_log.manage_own' => 'Track daily sadhana and yoga routine',
            'wellbeing.view_any' => 'View all wellbeing records (Admin/Lead Acharya)',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default System Roles and Initial Permissions
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'Super Admin' => [
            'description' => 'Unrestricted institute super administrator',
            'is_system' => true,
            'all_permissions' => true,
        ],
        'Admin' => [
            'description' => 'Institute administrator with complete operational access',
            'is_system' => true,
            'all_permissions' => true,
        ],
        'Moderator' => [
            'description' => 'Academic moderator overseeing course quality, enrollments, and payments',
            'is_system' => true,
            'permissions' => [
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
                'audit.view',
            ],
        ],
        'Teacher' => [
            'description' => 'Institute faculty member managing assigned courses, sessions, and grading',
            'is_system' => true,
            'permissions' => [
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
            ],
        ],
        'Guest Teacher' => [
            'description' => 'Time-limited guest teacher with scope limited to assigned batches/sessions',
            'is_system' => true,
            'permissions' => [
                'course.view',
                'batch.view',
                'session.manage',
                'attendance.view',
                'attendance.mark',
                'assignment.grade',
                'submission.view',
                'progress.view_any',
            ],
        ],
        'Student' => [
            'description' => 'Enrolled student with access to course materials, quizzes, and wellbeing',
            'is_system' => true,
            'permissions' => [
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
            ],
        ],
    ],
];
