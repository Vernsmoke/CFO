<?php

return [
    'catalog' => [
        'COLLEGE OF ACCOUNTANCY' => [
            'Bachelor of Science in Accountancy' => [],
            'Bachelor of Science in Accounting Information' => [],
        ],
        'COLLEGE OF ARTS AND SCIENCE' => [
            'Bachelor of Arts in Political Science' => [],
            'Bachelor of Arts in Journalism' => [],
            'Bachelor of Arts in Philosophy' => [],
            'Bachelor of Science in Psychology' => [],
            'Bachelor of Science in Social Work' => [],
        ],
        'COLLEGE OF BUSINESS EDUCATION' => [
            'Bachelor of Science in Business Administration' => [
                'Human Resources Management',
                'Financial Management',
                'Marketing Management',
            ],
            'Bachelor of Science in Office Administration' => [],
            'Bachelor of Science in Hospitality Management' => [],
            'Bachelor of Science in Tourism Management' => [],
        ],
        'COLLEGE OF CRIMINOLOGY' => [
            'Bachelor of Science in Criminology' => [],
        ],
        'COLLEGE OF EDUCATION' => [
            'Bachelor of Elementary Education' => [],
            'Bachelor of Secondary Early Childhood Education' => [],
            'Bachelor of Physical Education' => [],
            'Bachelor of Secondary Education' => [
                'English',
                'Filipino',
                'Mathematics',
                'Sciences',
                'Social Studies',
            ],
        ],
        'COLLEGE OF ENGINEERING AND ARCHITECTURE' => [
            'Bachelor of Science in Civil Engineering' => [],
            'Bachelor of Science in Electronics Engineering' => [],
            'Bachelor of Science in Computer Engineering' => [],
            'Bachelor of Science in Architecture' => [],
            'Bachelor of Science in Geodetic Engineering' => [],
        ],
        'COLLEGE OF INFORMATION TECHNOLOGY' => [
            'Bachelor of Science in Information Technology' => [],
            'Bachelor of Library and Information Science' => [],
        ],
        'COLLEGE OF LAW' => [
            'Juris Doctor (Bachelor of Laws)' => [],
        ],
        'COLLEGE OF MEDICINE AND ALLIED MEDICAL PROGRAMS' => [
            'Bachelor of Science in Medical Laboratory Science' => [],
            'Bachelor of Science in Radiologic Technology' => [],
            'Bachelor of Science in Pharmacy' => [],
            'Bachelor of Science in Physical Therapy' => [],
        ],
        'COLLEGE OF NURSING, PUBLIC HEALTH AND MIDWIFERY' => [
            'Bachelor of Science in Nursing' => [],
            'Bachelor of Science in Midwifery' => [],
            'Bachelor of Science in Public Health' => [],
        ],
    ],
    'verification_expire_minutes' => (int) env('CFO_EMAIL_VERIFICATION_EXPIRE_MINUTES', 60),
    'resend_cooldown_seconds' => (int) env('CFO_EMAIL_RESEND_COOLDOWN_SECONDS', 60),
    'password_reset_expire_minutes' => (int) env('CFO_PASSWORD_RESET_EXPIRE_MINUTES', 60),
];
