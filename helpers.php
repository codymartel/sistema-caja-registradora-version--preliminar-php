<?php
// helpers.php

if (!function_exists('formatMoney')) {
    function formatMoney($amount) {
        return "S/. " . number_format((float)$amount, 2);
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date) {
        return $date ? date("d/m/Y", strtotime($date)) : '-';
    }
}

if (!function_exists('formatTime')) {
    function formatTime($date) {
        return $date ? date("H:i", strtotime($date)) : '-';
    }
}