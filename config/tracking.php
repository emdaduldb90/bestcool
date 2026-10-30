<?php

return [
    'retention_days' => (int) env('TRACKING_RETENTION_DAYS', 180),
    'aggregate_retention_days' => (int) env('TRACKING_AGGREGATE_RETENTION_DAYS', 730),
    'audit_retention_days' => (int) env('TRACKING_AUDIT_RETENTION_DAYS', 365),
];
