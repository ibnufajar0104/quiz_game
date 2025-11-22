<?php
// =====================
// KONEKSI DATABASE
// =====================
$host = 'localhost';        // ganti sesuai settingmu
$db   = 'quiz';    // ganti nama DB
$user = 'root';        // ganti user
$pass = '';         // ganti password

$dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Koneksi DB gagal: " . $e->getMessage());
}

// =====================
// AMBIL CONFIG KUIS
// =====================
$stmt = $pdo->query("
    SELECT point_per_question, passing_score, quiz_duration, idle_limit
    FROM quiz_config
    WHERE is_active = 1
    LIMIT 1
");
$config = $stmt->fetch();

if (!$config) {
    // fallback kalau belum ada data di quiz_config
    $config = [
        'point_per_question' => 10,
        'passing_score'      => 80,
        'quiz_duration'      => 60,
        'idle_limit'         => 60,
    ];
}

// jumlah soal yang akan ditampilkan
$totalQuestions = 10; // bisa kamu ubah nanti, cukup ubah angka ini saja
$maxScore       = $totalQuestions * (int)$config['point_per_question'];
$durationSeconds = (int)$config['quiz_duration'];
$passingScore    = (int)$config['passing_score'];

// label waktu untuk ditampilkan di teks
if ($durationSeconds % 60 === 0) {
    $minutes = $durationSeconds / 60;
    $durationLabel = $minutes . ' menit';
} else {
    $durationLabel = $durationSeconds . ' detik';
}

// =====================
// AMBIL BANK SOAL AKTIF
// =====================
$stmt = $pdo->query("
    SELECT id, question_text, option_a, option_b, option_c, option_d,
           correct_option, difficulty
    FROM quiz_questions
    WHERE is_active = 1
");

$rows = $stmt->fetchAll();

$optionMap = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3];
$questionBank = [];

foreach ($rows as $r) {
    $questionBank[] = [
        'text'        => $r['question_text'],
        'options'     => [
            $r['option_a'],
            $r['option_b'],
            $r['option_c'],
            $r['option_d'],
        ],
        'correctIndex' => $optionMap[$r['correct_option']] ?? 0,
        'difficulty'  => (int)$r['difficulty'], // 1 = biasa, 2 = sulit
    ];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <title>Kuis Harjad Tanah Laut</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <!-- Bootstrap 5 CSS -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />

    <!-- CONFIG & BANK SOAL DARI DATABASE -->
    <script>
        // Konfigurasi dari database
        const POINT_PER_QUESTION = <?= (int)$config['point_per_question']; ?>;
        const PASSING_SCORE = <?= (int)$passingScore; ?>;
        const QUIZ_DURATION = <?= (int)$config['quiz_duration']; ?>; // detik
        const IDLE_LIMIT = <?= (int)$config['idle_limit']; ?> * 1000; // detik -> ms
        const TOTAL_QUESTIONS = <?= (int)$totalQuestions; ?>;

        // Bank soal dari database
        const QUESTION_BANK = <?= json_encode($questionBank, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>

    <style>
        /* ======================
         GLOBAL
      ====================== */
        * {
            box-sizing: border-box;
            font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont,
                "Segoe UI", Arial, sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            color: #0c4a6e;
        }

        .page-wrapper {
            width: 100%;
            max-width: 1100px;
            padding: 16px;
            margin: 0 auto;
        }

        /* ======================
         HEADER LOGO & TITLE
      ====================== */
        .header-container {
            margin-bottom: 20px;
        }

        .header-photo {
            max-width: 100%;
        }

        .header-photo img {
            display: block;
            width: 100%;
            height: auto;
            object-fit: contain;
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.15));
        }

        .header-logo img {
            max-width: 100%;
            max-height: 140px;
            width: auto;
            height: auto;
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.15));
        }

        /* Foto Bupati & Camat (lebih besar, beda ukuran) */
        .photo-bupati img {
            max-width: 360px;
            margin-inline: auto;
        }

        .photo-camat img {
            max-width: 360px;
            margin-inline: auto;
        }

        @media (max-width: 992px) {
            .photo-bupati img {
                max-width: 220px;
            }

            .photo-camat img {
                max-width: 180px;
            }

            .header-logo img {
                max-height: 130px;
            }
        }

        @media (max-width: 768px) {
            .header-logo img {
                max-height: 120px;
            }
        }

        @media (max-width: 480px) {
            .page-wrapper {
                padding: 12px;
            }

            .header-logo img {
                max-height: 100px;
            }
        }

        .title-block {
            text-align: center;
            margin-bottom: 16px;
        }

        .title-block h1 {
            margin: 0 0 8px 0;
            font-size: 1.6rem;
            font-weight: 800;
            color: #0c4a6e;
            text-shadow: 0 2px 4px rgba(255, 255, 255, 0.3);
        }

        .title-block .subtitle {
            font-size: 0.95rem;
            color: #075985;
            font-style: italic;
        }

        @media (max-width: 640px) {
            .title-block h1 {
                font-size: 1.4rem;
            }

            .title-block .subtitle {
                font-size: 0.85rem;
            }
        }

        /* ======================
         GLASS CARD
      ====================== */
        .glass {
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid rgba(14, 165, 233, 0.2);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 20px 60px rgba(14, 165, 233, 0.15);
            backdrop-filter: blur(12px);
            position: relative;
            transition: all 0.3s ease;
            max-width: 100%;
        }

        .glass:hover {
            box-shadow: 0 25px 70px rgba(14, 165, 233, 0.25);
            transform: translateY(-2px);
        }

        @media (max-width: 640px) {
            .glass {
                padding: 16px;
                border-radius: 18px;
            }
        }

        /* ======================
         START CARD
      ====================== */
        #startCard {
            margin-bottom: 18px;
        }

        .start-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #0c4a6e;
        }

        .start-text {
            font-size: 0.9rem;
            color: #0f172a;
            margin-bottom: 14px;
        }

        .start-rules {
            font-size: 0.85rem;
            color: #0369a1;
            margin-bottom: 14px;
        }

        .start-rules ul {
            padding-left: 18px;
            margin: 6px 0 0 0;
        }

        .start-rules li {
            margin-bottom: 4px;
        }

        @media (max-width: 640px) {
            .start-title {
                font-size: 1rem;
            }

            .start-text,
            .start-rules {
                font-size: 0.85rem;
            }
        }

        /* ======================
         TIMER BESAR
      ====================== */
        .timer-wrapper {
            margin: 0 0 10px 0;
            text-align: center;
            display: none;
            /* hanya muncul saat kuis berjalan */
        }

        .timer-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 10px 24px;
            border-radius: 999px;
            font-size: 1rem;
            font-weight: 600;
            background: #e0f2fe;
            color: #0c4a6e;
            border: 1px solid #bae6fd;
        }

        .timer-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        #timerText {
            font-variant-numeric: tabular-nums;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .timer-warning {
            background: #fee2e2;
            border-color: #fecaca;
            color: #b91c1c;
        }

        @media (max-width: 480px) {
            .timer-badge {
                width: 100%;
                padding-inline: 12px;
                font-size: 0.9rem;
            }

            #timerText {
                font-size: 1.3rem;
            }
        }

        /* ======================
         QUIZ
      ====================== */
        #quizCard {
            margin-bottom: 18px;
            display: none;
            max-height: 60vh;
            /* agar 1 layar */
            overflow-y: auto;
            /* scrollable */
        }

        .quiz-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .score-info {
            font-size: 0.9rem;
            font-weight: 600;
            color: #0c4a6e;
            background: rgba(224, 242, 254, 0.5);
            padding: 8px 16px;
            border-radius: 999px;
            border: 2px solid #bae6fd;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #0ea5e9;
            color: #0e7490;
            box-shadow: none;
        }

        .btn-outline:hover {
            background: #0ea5e9;
            color: white;
        }

        .question {
            background: rgba(240, 249, 255, 0.6);
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 12px;
            border: 2px solid rgba(186, 230, 253, 0.8);
            transition: all 0.3s ease;
        }

        .question:hover {
            background: rgba(224, 242, 254, 0.8);
            border-color: #7dd3fc;
        }

        .question-text {
            font-size: 0.95rem;
            margin-bottom: 8px;
            font-weight: 600;
            color: #0c4a6e;
        }

        .options label {
            display: block;
            font-size: 0.88rem;
            margin-bottom: 5px;
            cursor: pointer;
            color: #0e7490;
            transition: color 0.2s;
        }

        .options label:hover {
            color: #0369a1;
        }

        .options input[type="radio"] {
            margin-right: 6px;
            accent-color: #0ea5e9;
        }

        .quiz-actions {
            margin-top: 16px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 0.9rem;
        }

        button {
            cursor: pointer;
            border: 0;
            border-radius: 999px;
            padding: 10px 24px;
            font-size: 0.95rem;
            font-weight: 700;
            background: linear-gradient(90deg, #0ea5e9, #0284c7);
            color: white;
            box-shadow: 0 4px 16px rgba(14, 165, 233, 0.4);
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(14, 165, 233, 0.6);
        }

        button:active {
            transform: scale(0.97);
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }

        #statusText {
            margin-top: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #0369a1;
            padding: 10px;
            background: rgba(240, 249, 255, 0.7);
            border-radius: 12px;
            border-left: 4px solid #0ea5e9;
        }

        @media (max-width: 640px) {
            .quiz-header {
                flex-direction: column;
                align-items: stretch;
            }

            .score-info {
                width: 100%;
                text-align: center;
            }

            .quiz-actions {
                flex-direction: column;
                align-items: stretch;
            }

            button {
                width: 100%;
                text-align: center;
            }

            .question-text {
                font-size: 0.9rem;
            }

            .options label {
                font-size: 0.85rem;
            }
        }

        /* HP: kalau mau pakai CSS tambahan, bupati & camat tetap disembunyikan */
        @media (max-width: 767.98px) {

            .header-photo.photo-bupati,
            .header-photo.photo-camat {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    <div class="page-wrapper">
        <!-- HEADER: FOTO BUPATI - LOGO - FOTO CAMAT (Bootstrap grid) -->
        <div
            class="header-container row justify-content-center align-items-center text-center g-3">
            <!-- Bupati: hanya tampil di md ke atas -->
            <div class="col-md-4 header-photo photo-bupati d-none d-md-block">
                <img src="wkdh.png" alt="Bupati" />
            </div>

            <!-- Logo: selalu tampil, di HP jadi full width -->
            <div class="col-12 col-md-4 header-logo">
                <img src="logo.png" alt="Logo Hari Jadi Tanah Laut" />
            </div>

            <!-- Camat: hanya tampil di md ke atas -->
            <div class="col-md-4 header-photo photo-camat d-none d-md-block">
                <img src="camat.png" alt="Camat Pelaihari" />
            </div>
        </div>

        <div class="title-block">
            <h1>Kuis Hari Jadi Kabupaten Tanah Laut ke-60</h1>
            <div class="subtitle">
                "Bagawi Sabarataan Tanah Laut, Simpun Bakamajuan"
            </div>
        </div>

        <!-- AUDIO -->
        <audio id="startSound" src="start.mp3"></audio>
        <audio id="tickSound" src="tick.mp3" loop></audio>
        <audio id="warningSound" src="warning.mp3"></audio>
        <audio id="timeUpSound" src="timeup.mp3"></audio>
        <audio id="winSound" src="win.mp3"></audio>

        <!-- KARTU MULAI -->
        <div id="startCard" class="glass">
            <div class="start-title">Selamat Datang di Kuis Harjad Tanah Laut</div>
            <div class="start-text">
                Jawab pertanyaan seputar Kabupaten Tanah Laut dan Hari Jadi ke-60.
                Waktu mengerjakan <strong><?= htmlspecialchars($durationLabel, ENT_QUOTES, 'UTF-8'); ?></strong>. Siap-siap ya! 😄
            </div>
            <div class="start-rules">
                Aturan singkat:
                <ul>
                    <li>Tekan tombol <strong>Mulai Kuis</strong> untuk mulai main.</li>
                    <li>
                        Waktu ngerjain cuma
                        <strong><?= htmlspecialchars($durationLabel, ENT_QUOTES, 'UTF-8'); ?></strong>,
                        jadi langsung gas aja.
                    </li>
                    <li>
                        Nilai maksimal <strong><?= (int)$maxScore; ?></strong>, tiap
                        jawaban benar dapat <?= (int)$config['point_per_question']; ?> poin.
                    </li>
                    <li>
                        Kalau nilaimu <strong><?= (int)$maxScore; ?></strong>, langsung
                        dapat hadiah. Mantap!
                    </li>
                    <li>
                        Kalau nilai kamu <strong>di atas <?= (int)$passingScore; ?></strong>,
                        lanjut ke game dart buat dapat hadiah tambahan.
                    </li>
                    <li>
                        Kalau nilainya <strong>di bawah <?= (int)$passingScore; ?></strong>,
                        sayang banget… kamu gagal. Tapi tenang, bisa coba lagi kok.
                    </li>
                </ul>
            </div>
            <button id="startBtn">Mulai Kuis</button>
        </div>

        <!-- TIMER BESAR (DI ATAS CARD SOAL) -->
        <div id="timerWrapper" class="timer-wrapper">
            <div class="timer-badge" id="timerBadge">
                <span class="timer-label">⏱️ Sisa waktu:</span>
                <span id="timerText">01:00</span>
            </div>
        </div>

        <!-- KARTU KUIS (SCROLLABLE) -->
        <div id="quizCard" class="glass">
            <div class="quiz-header">
                <div class="score-info">
                    Nilai: <span id="scoreValue">0</span> /
                    <span id="maxScoreValue">0</span>
                </div>
                <button id="backToStartBtn" type="button" class="btn-outline">
                    Kembali ke Menu Awal
                </button>
            </div>

            <div id="quizContainer"></div>

            <div class="quiz-actions">
                <button id="submitBtn">Kumpulkan Jawaban</button>
            </div>
            <div id="statusText"></div>
        </div>
    </div>

    <script>
        /* ===========================
         BANK SOAL (DARI DB) -> acak TOTAL_QUESTIONS
      =========================== */

        function shuffle(arr) {
            for (let i = arr.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [arr[i], arr[j]] = [arr[j], arr[i]];
            }
            return arr;
        }

        const QUESTIONS = shuffle([...QUESTION_BANK]).slice(0, TOTAL_QUESTIONS);
        const MAX_SCORE = QUESTIONS.length * POINT_PER_QUESTION;

        if (QUESTIONS.length === 0) {
            alert("Belum ada soal aktif di database.");
        }

        document.getElementById("maxScoreValue").textContent = MAX_SCORE;

        /* ===========================
           RENDER SOAL
        =========================== */
        const quizContainer = document.getElementById("quizContainer");

        QUESTIONS.forEach((q, i) => {
            const box = document.createElement("div");
            box.className = "question";
            box.innerHTML = `
          <div class="question-text">${i + 1}. ${q.text}</div>
          <div class="options">
              ${q.options
                .map(
                  (opt, idx) => `
                  <label>
                      <input type="radio" name="q${i}" value="${idx}">
                      ${opt}
                  </label>
              `
                )
                .join("")}
          </div>
        `;
            quizContainer.appendChild(box);
        });

        /* ===========================
           ELEMENT & STATE
        =========================== */
        const startCard = document.getElementById("startCard");
        const startBtn = document.getElementById("startBtn");
        const quizCard = document.getElementById("quizCard");
        const statusText = document.getElementById("statusText");
        const scoreValueEl = document.getElementById("scoreValue");
        const submitBtn = document.getElementById("submitBtn");
        const timerTextEl = document.getElementById("timerText");
        const timerBadge = document.getElementById("timerBadge");
        const timerWrapper = document.getElementById("timerWrapper");
        const backToStartBtn = document.getElementById("backToStartBtn");

        const startSound = document.getElementById("startSound");
        const tickSound = document.getElementById("tickSound");
        const warningSound = document.getElementById("warningSound");
        const timeUpSound = document.getElementById("timeUpSound");
        const winSound = document.getElementById("winSound");

        let timeLeft = QUIZ_DURATION;
        let timerInterval = null;
        let quizFinished = false;
        let warningPlayed = false;

        let idleTimer = null;

        /* ===========================
           TIMER
        =========================== */
        function formatTime(seconds) {
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            const mm = m.toString().padStart(2, "0");
            const ss = s.toString().padStart(2, "0");
            return `${mm}:${ss}`;
        }

        function updateTimerDisplay() {
            timerTextEl.textContent = formatTime(timeLeft);
        }

        function stopTimerAndSound() {
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            try {
                tickSound.pause();
                tickSound.currentTime = 0;
            } catch (e) {}
        }

        function handleTimeUp() {
            stopTimerAndSound();
            try {
                timeUpSound.currentTime = 0;
                timeUpSound.play();
            } catch (e) {}
            if (!quizFinished) {
                finishQuiz(true);
            }
        }

        function startTimer() {
            timeLeft = QUIZ_DURATION;
            warningPlayed = false;
            timerBadge.classList.remove("timer-warning");
            updateTimerDisplay();

            timerInterval = setInterval(() => {
                timeLeft--;
                if (timeLeft <= 0) {
                    timeLeft = 0;
                    updateTimerDisplay();
                    handleTimeUp();
                } else {
                    updateTimerDisplay();
                    if (timeLeft <= 10 && !warningPlayed) {
                        warningPlayed = true;
                        timerBadge.classList.add("timer-warning");
                        try {
                            warningSound.currentTime = 0;
                            warningSound.play();
                        } catch (e) {}
                    }
                }
            }, 1000);
        }

        /* ===========================
           IDLE TIMER (DI LUAR COUNTDOWN)
        =========================== */
        function clearIdleTimer() {
            if (idleTimer) {
                clearTimeout(idleTimer);
                idleTimer = null;
            }
        }

        function resetIdleTimer() {
            clearIdleTimer();
            // hanya aktif kalau kuis sedang tampil
            if (quizCard.style.display === "block") {
                idleTimer = setTimeout(() => {
                    // idle -> kembali ke menu awal
                    backToStart();
                }, IDLE_LIMIT);
            }
        }

        /* ===========================
           LOGIKA KUIS
        =========================== */
        function disableQuizInputs() {
            const radios = quizContainer.querySelectorAll('input[type="radio"]');
            radios.forEach((r) => (r.disabled = true));
            submitBtn.disabled = true;
            submitBtn.textContent = "Selesai";
        }

        function finishQuiz(fromTimer = false) {
            if (quizFinished) return;

            let score = 0;
            let answered = 0;

            QUESTIONS.forEach((q, i) => {
                const sel = document.querySelector(`input[name="q${i}"]:checked`);
                if (sel) {
                    answered++;
                    if (parseInt(sel.value, 10) === q.correctIndex) {
                        score += POINT_PER_QUESTION;
                    }
                }
            });

            // Jika manual submit dan belum semua terjawab, minta lengkapi dulu
            if (!fromTimer && answered < QUESTIONS.length) {
                statusText.textContent =
                    "Jawab semua soal dulu. Terjawab: " +
                    answered +
                    " dari " +
                    QUESTIONS.length +
                    ".";
                return;
            }

            quizFinished = true;
            stopTimerAndSound();
            scoreValueEl.textContent = score;

            let message = "";
            if (fromTimer) {
                message += "Waktu habis! ";
            }

            message += "Nilai " + score + ". ";

            if (score >= PASSING_SCORE) {
                try {
                    winSound.currentTime = 0;
                    winSound.play();
                } catch (e) {}
                message +=
                    "Selamat! Terima kasih sudah mengikuti kuis Hari Jadi Tanah Laut.";
            } else {
                message +=
                    "Belum mencapai " +
                    PASSING_SCORE +
                    ". Terima kasih sudah ikut bermain.";
            }

            statusText.textContent = message;
            disableQuizInputs();
        }

        submitBtn.addEventListener("click", () => {
            finishQuiz(false);
            resetIdleTimer();
        });

        /* ===========================
           KEMBALI KE MENU AWAL
        =========================== */
        function backToStart() {
            stopTimerAndSound();
            clearIdleTimer();

            quizFinished = false;
            timeLeft = QUIZ_DURATION;
            updateTimerDisplay();
            timerBadge.classList.remove("timer-warning");

            // reset pilihan & tombol
            const radios = quizContainer.querySelectorAll('input[type="radio"]');
            radios.forEach((r) => {
                r.checked = false;
                r.disabled = false;
            });
            submitBtn.disabled = false;
            submitBtn.textContent = "Kumpulkan Jawaban";
            statusText.textContent = "";

            // tampilkan start, sembunyikan kuis & timer
            quizCard.style.display = "none";
            timerWrapper.style.display = "none";
            startCard.style.display = "block";
        }

        backToStartBtn.addEventListener("click", () => {
            backToStart();
        });

        /* ===========================
           START FLOW
        =========================== */
        function startQuiz() {
            if (QUESTIONS.length === 0) {
                statusText.textContent = "Belum ada soal aktif. Hubungi petugas.";
                return;
            }

            quizFinished = false;
            scoreValueEl.textContent = "0";
            statusText.textContent = "";

            const radios = quizContainer.querySelectorAll('input[type="radio"]');
            radios.forEach((r) => {
                r.checked = false;
                r.disabled = false;
            });

            submitBtn.disabled = false;
            submitBtn.textContent = "Kumpulkan Jawaban";

            startCard.style.display = "none";
            quizCard.style.display = "block";
            timerWrapper.style.display = "block";
            updateTimerDisplay();

            try {
                startSound.currentTime = 0;
                startSound.play();
            } catch (e) {}

            try {
                tickSound.currentTime = 0;
                tickSound.play();
            } catch (e) {}

            startTimer();
            resetIdleTimer();
        }

        startBtn.addEventListener("click", startQuiz);

        // Event untuk reset idle timer (aktivitas user di area kuis)
        ["click", "keydown", "mousemove", "scroll", "touchstart"].forEach(
            (evt) => {
                quizCard.addEventListener(evt, resetIdleTimer);
            }
        );

        // Set display awal timer (sebelum kuis dimulai)
        updateTimerDisplay();
    </script>
</body>

</html>