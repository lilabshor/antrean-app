<?php

declare(strict_types=1);

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/helpers/functions.php";

$today = date ("y-m-d");

try {
    $stmt = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY code ASC");
    $services = $stmt->fetchAll();
}catch (PDOException $e) {
    $services = [];
}

?>
<!DOCTYPE html >
<html>
<head>
   <title>Display ruang tunggu</title>
    <style>
        /*            ini bagian front end tolong di isi ya wahai assistent ai*/
    </style>
</head>
<body>

<div class="header">
    <h1>Layar Informasi Antrean</h1>
    <div class="clock" id="liveClock">--:--:-- WIB</div>
</div>

<div class="main-container">
    <div class="calling-section" id="callingGrid">
        <?php foreach ($services as $s) : ?>
        <div class="calling-card" id="card-service-<?= (int)$s["id"] ?>">
            <div class="card-service"><?= e($s["nama"]) ?></div>
            <div class="card-code" id="code-svc-<?= (int)$s["id"] ?>">---</div>
            <div class="card-counter" id="counter-svc-<?=(int)$s["id"] ?>">Menunggu panggilan</div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="waiting-section">
        <div class="waiting-header">Antran menunggu berikutnya</div>
        <ul class="waiting-list" id="waitinglist">
            <li style="color : #64748b; text-align : center; padding-top: 2rem;">Membuat data antrean.../</li>
        </ul>
    </div>
</div>

<div class="footer">
    Mohon tertib dan memperhatikan nomor antreab serta menuju loket yang di tentukan
</div>

<script>
    function updateClock() {
        const now = new Date();
        const str = now.toLocaleTimeString("id-ID") + " WIB";
        document.getElementById("LiveClock").innerText = str;
    }
    setInterval(updateClock, 1000);
    updateClock()

    let lasCalledId = null;

    function speakQueue(queueCode, counterNum) {
        if (!("speechSynthesis" in window)) return;

        const formatterdCode = queueCode.replace("-", " ");
        const text = `nomor antrean ${formatterdCode}, silahkan menuju locket ${counterNum}`;

        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = "id-ID";
        utterance.rate = 0.9;
        utterance.pitch = 1.0;
        window.speechSynthesis.speak(utterance);

        async function fetchQueueData() {
            try {
                const res = await fetch ("api/get_current_queue.php?date=<?= e($today) ?>");
                const data = await res.json();

                if(data.status === "success") {
                    data.calling.forEach(item => {
                        const card = document.getElementById(`card-service-${item.service_id}`);
                        const codeEl = document.getElementById(`code-svc-${item.service_id}`);
                        const counterEl = document.getElementById(`counter-svc-${item.service_id}`);

                        if (card && codeEl && counterEl) {
                            codeEl.innerText = item.queue_code;
                            counterEl.innerText = item.counter_called ? `LOKET ${item.counter_called}` : "SEDANG DILAYANI";
                            card.classList.add("active");
                        }
                    });

                    if (data.latest_call && data.lastest_call.id !== lastCalledId) {
                        lastCalledId = data.lastest_call.id;
                        speakQueue(data.lastest_call.queue_code, data.latest_call.counter_called || 1);
                    }

                    const waitingListEl = document.getElementById("waitingList");
                    if(data.waiting.length === 0) {
                        waitingListEl.innerHTML = "<li style="color:#64748b;text-align:center;padding:2rem;">Tidak ada antrean menunggu</li>
                    }else {
                        waitingListEl.innerHTML = data.waiting.map(item =>
                            <li class="waiting-item">
                                <span class="waiting-code">${item.queue_code}</span>
                                <span class="waiting-service">${item.service_name} (${item.time_slot})</span>
                            </li>
                        ).join("")
                    }

                }
            } catch (err) {
                console.error("gagal mengambil data antrean :", err);
            }
        }
    }
        setInterval(fetchQueueData, 3000);
        fetchQueueData();

</script>
</body>
</html>
