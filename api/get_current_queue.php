<?php

declare(strict_types=1);

require_once __DIR__ ."/../config/database.php";
require_once __DIR__ ."/../helpers/functions.php";

$date = filter_input(INPUT_GET, "date", FILTER_DEFAULT) ?? date("y-m-d");

if (!preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $date)) {
    json_response(["status" => "error", "message" => "Format tanggal tidak valid"], 400);
}

try {
    $stmt_calling = $pdo -> prepare (
        "SELECT q.id, q.service_id, q.queue_code, q.counter_called, q.status, q.called_at, s.name AS service_name
        FROM queues q
        JOIN services s ON q.service_id = s.id
        WHERE q.appointment_date = ? AND q.status = CALLING
        ORDER BY q.called_at DESC"
    );

    $stmt_calling->execute([$date]);
    $calling_queues = $stmt_calling -> fetchAll();
    $latest_call = $calling_queues[0] ?? null;

    $stmt_waiting = $pdo -> prepare(
        "SELECT q.id, q.service_id, q.queue_code, q.time_slot, s.name AS  service_name
               FROM queues q 
               JOIN services s ON q.service_id = s.id 
               WHERE q.appointment_date = ? AND q.status = WAITING
               ORDER BY q.id ASC
               LIMIT 10"
    );

    $stmt_waiting->execute([$date]);
    $waiting_queues = $stmt_waiting -> fetchAll();

    json_response([
        "status" => "success",
        "date" => $date,
        "latest_call" => $latest_call,
        "calling" => $calling_queues,
        "waiting" => $waiting_queues
    ]);
}catch (PDOException $e) {
    error_log("API Error : " .$e->getmessage());
    json_response(["status" => "error", "message" => "terjadi kesalahan server"], 500);
}
