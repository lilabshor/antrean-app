<?php

declare(strict_types=1);

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/helpers/functions.php";

$today = date("Y-m-d");

try {
    $stmt = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY code ASC");
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    $services = [];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Display Ruang Tunggu</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            background: #1e293b;
            border-bottom: 2px solid #2563eb;
        }

        .header h1 {
            font-size: 1.3rem;
            color: #f8fafc;
            letter-spacing: 0.5px;
        }

        .clock {
            font-size: 1.4rem;
            font-weight: 700;
            color: #38bdf8;
            letter-spacing: 1px;
        }

        .main-container {
            display: flex;
            gap: 1rem;
            padding: 1rem 1.5rem;
            flex: 1;
        }

        .calling-section {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            flex: 2;
            align-content: start;
        }

        .calling-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 1.2rem;
            text-align: center;
            transition: border-color 0.3s;
        }

        .calling-card.active {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37,99,235,0.3);
        }

        .card-service {
            font-size: 0.8rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }

        .card-code {
            font-size: 2.2rem;
            font-weight: 700;
            color: #38bdf8;
            letter-spacing: 2px;
            margin-bottom: 0.4rem;
        }

        .card-counter {
            font-size: 0.8rem;
            color: #64748b;
        }

        .calling-card.active .card-counter {
            color: #34d399;
            font-weight: 600;
        }

        .waiting-section {
            flex: 1;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 10px;
            overflow: hidden;
            max-height: calc(100vh - 130px);
            display: flex;
            flex-direction: column;
        }

        .waiting-header {
            background: #2563eb;
            color: #fff;
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .waiting-list {
            list-style: none;
            overflow-y: auto;
            flex: 1;
            padding: 0.5rem;
        }

        .waiting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.6rem 0.5rem;
            border-bottom: 1px solid #1e293b;
            font-size: 0.85rem;
        }

        .waiting-code {
            font-weight: 700;
            color: #f8fafc;
        }

        .waiting-service {
            color: #94a3b8;
            font-size: 0.78rem;
        }

        .footer {
            text-align: center;
            padding: 0.6rem;
            background: #1e293b;
            font-size: 0.78rem;
            color: #64748b;
            border-top: 1px solid #334155;
        }
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
            <div class="card-service"><?= e($s["name"]) ?></div>
            <div class="card-code" id="code-svc-<?= (int)$s["id"] ?>">---</div>
            <div class="card-counter" id="counter-svc-<?= (int)$s["id"] ?>">Menunggu panggilan</div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="waiting-section">
        <div class="waiting-header">Antrean menunggu berikutnya</div>
        <ul class="waiting-list" id="waitingList">
            <li style="color:#64748b; text-align:center; padding-top:2rem;">Memuat data antrean...</li>
        </ul>
    </div>
</div>

<div class="footer">
    Mohon tertib dan memperhatikan nomor antrean serta menuju loket yang ditentukan.
</div>

<script>
    function updateClock() {
        const now = new Date();
        const str = now.toLocaleTimeString("id-ID") + " WIB";
        document.getElementById("liveClock").innerText = str;
    }
    setInterval(updateClock, 1000);
    updateClock();

    let lastCalledId = null;

    function speakQueue(queueCode, counterNum) {
        if (!("speechSynthesis" in window)) return;

        const formattedCode = queueCode.replace(/-/g, " ");
        const text = `nomor antrean ${formattedCode}, silakan menuju loket ${counterNum}`;

        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = "id-ID";
        utterance.rate = 0.9;
        utterance.pitch = 1.0;
        window.speechSynthesis.speak(utterance);
    }

    async function fetchQueueData() {
        try {
            const res = await fetch("api/get_current_queue.php?date=<?= e($today) ?>");
            const data = await res.json();

            if (data.status === "success") {
                data.calling.forEach(item => {
                    const card      = document.getElementById(`card-service-${item.service_id}`);
                    const codeEl    = document.getElementById(`code-svc-${item.service_id}`);
                    const counterEl = document.getElementById(`counter-svc-${item.service_id}`);

                    if (card && codeEl && counterEl) {
                        codeEl.innerText    = item.queue_code;
                        counterEl.innerText = item.counter_called ? `LOKET ${item.counter_called}` : "SEDANG DILAYANI";
                        card.classList.add("active");
                    }
                });

                if (data.latest_call && data.latest_call.id !== lastCalledId) {
                    lastCalledId = data.latest_call.id;
                    speakQueue(data.latest_call.queue_code, data.latest_call.counter_called || 1);
                }

                const waitingListEl = document.getElementById("waitingList");
                if (data.waiting.length === 0) {
                    waitingListEl.innerHTML = `<li style="color:#64748b;text-align:center;padding:2rem;">Tidak ada antrean yang menunggu</li>`;
                } else {
                    waitingListEl.innerHTML = data.waiting.map(item => `
                        <li class="waiting-item">
                            <span class="waiting-code">${item.queue_code}</span>
                            <span class="waiting-service">${item.service_name} (${item.time_slot})</span>
                        </li>
                    `).join("");
                }
            }
        } catch (err) {
            console.error("Gagal mengambil data antrean:", err);
        }
    }

    setInterval(fetchQueueData, 3000);
    fetchQueueData();
</script>
</body>
</html>
