<?php
function normalizePubDate($rawDate) {
    try {
        $date = new DateTime($rawDate);
        return $date->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return ""; 
    }
}
