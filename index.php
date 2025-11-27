<?php
// =====================
// KONEKSI DATABASE
// =====================
$host = 'localhost';        // ganti sesuai settingmu
$db   = 'quiz';             // ganti nama DB
$user = 'root';             // ganti user
$pass = '';                 // ganti password

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
// pastikan di tabel quiz_config ada kolom: difficulty (1 = biasa, 2 = sulit)
$stmt = $pdo->query("
    SELECT point_per_question,
           passing_score,
           quiz_duration,
           idle_limit,
           difficulty
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
        'difficulty'         => 1, // default: biasa
    ];
}

// normalisasi difficulty dari config
$difficultyConfig = isset($config['difficulty']) ? (int)$config['difficulty'] : 1;
// kalau ada nilai aneh, fallback ke 1 (biasa)
if ($difficultyConfig !== 1 && $difficultyConfig !== 2) {
    $difficultyConfig = 1;
}

// jumlah soal yang akan ditampilkan
$totalQuestions  = 10; // bisa kamu ubah nanti, cukup ubah angka ini saja
$maxScore        = $totalQuestions * (int)$config['point_per_question'];
$durationSeconds = (int)$config['quiz_duration'];
$passingScore    = (int)$config['passing_score'];

// label waktu untuk ditampilkan di teks
if ($durationSeconds % 60 === 0) {
    $minutes       = $durationSeconds / 60;
    $durationLabel = $minutes . ' menit';
} else {
    $durationLabel = $durationSeconds . ' detik';
}

// =====================
// AMBIL BANK SOAL AKTIF
// =====================
// kalau mau STRICT: hanya ambil soal dengan difficulty sesuai config
// 1 = biasa, 2 = sulit
if ($difficultyConfig === 1 || $difficultyConfig === 2) {
    $stmt = $pdo->prepare("
        SELECT id,
               question_text,
               option_a,
               option_b,
               option_c,
               option_d,
               correct_option,
               difficulty
        FROM quiz_questions
        WHERE is_active = 1
          AND difficulty = :difficulty
    ");
    $stmt->execute([':difficulty' => $difficultyConfig]);
} else {
    // fallback: kalau difficultyConfig aneh, ambil semua soal aktif
    $stmt = $pdo->query("
        SELECT id,
               question_text,
               option_a,
               option_b,
               option_c,
               option_d,
               correct_option,
               difficulty
        FROM quiz_questions
        WHERE is_active = 1
    ");
}

$rows = $stmt->fetchAll();

$optionMap    = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3];
$questionBank = [];

foreach ($rows as $r) {
    $questionBank[] = [
        'text'         => $r['question_text'],
        'options'      => [
            $r['option_a'],
            $r['option_b'],
            $r['option_c'],
            $r['option_d'],
        ],
        'correctIndex' => $optionMap[$r['correct_option']] ?? 0,
        'difficulty'   => (int)$r['difficulty'], // 1 = biasa, 2 = sulit
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
        /* ======================= GLOBAL ======================= */
        * {
            box-sizing: border-box;
            font-family: "Inter", system-ui, sans-serif;
        }

        html,
        body {
            min-height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            /* biar horizontal nggak ada scroll */
            overflow-y: auto;
            /* vertikal boleh scroll */
        }

        body {
            /* background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
            color: #0c4a6e; */
        }

        .page-wrapper {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            padding: 12px 12px 24px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ======================= HEADER & TITLE ======================= */
        .header-container {
            flex-shrink: 0;
            padding: 12px 16px 8px;
        }

        .header-photos {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-bottom: 12px;
        }

        .header-photo img {
            width: 100%;
            max-width: 240px;
            height: auto;
            object-fit: contain;
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.15));
        }

        .header-logo img {
            width: 100%;
            max-width: 100px;
            height: auto;
            object-fit: contain;
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.15));
        }

        .title-block {
            text-align: center;
        }

        .title-block h1 {
            margin: 0 0 4px;
            font-size: 1.3rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .title-block .subtitle {
            font-size: 0.85rem;
            color: #075985;
            font-style: italic;
        }

        /* ======================= TIMER ======================= */
        .timer-wrapper {
            position: sticky;
            /* selalu nempel di atas saat scroll */
            top: 0;
            z-index: 20;
            text-align: center;
            padding: 8px 0;
            margin-top: 8px;
            display: none;
        }

        .timer-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 999px;
            background: #e0f2fe;
            border: 2px solid #0ea5e9;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .timer-badge.timer-warning {
            background: #fef3c7;
            border-color: #f59e0b;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        #timerText {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0c4a6e;
        }

        /* ======================= CONTENT AREA ======================= */
        .content-wrapper {
            flex: 1;
            padding: 16px 0;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        /* ======================= GLASS CARD ======================= */
        .glass {
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid rgba(14, 165, 233, 0.2);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 18px 40px rgba(14, 165, 233, 0.15);
            width: 100%;
            max-width: 900px;
        }

        /* ======================= START PAGE ======================= */
        #startCard .start-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #0c4a6e;
        }

        #startCard .start-text {
            font-size: 0.9rem;
            margin-bottom: 12px;
            line-height: 1.5;
        }

        #startCard .start-rules {
            font-size: 0.85rem;
            line-height: 1.6;
        }

        #startCard .start-rules ul {
            margin: 8px 0;
            padding-left: 20px;
        }

        #startCard .start-rules li {
            margin-bottom: 6px;
        }

        /* ======================= QUIZ CARD ======================= */
        #quizCard {
            display: none;
        }

        /* ======================= QUIZ HEADER ======================= */
        .quiz-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .score-info {
            padding: 6px 14px;
            font-size: 0.85rem;
            background: #e0f2fe;
            border-radius: 999px;
            border: 2px solid #0ea5e9;
            font-weight: 600;
        }

        .btn-outline {
            border: 2px solid #0ea5e9;
            padding: 6px 14px;
            font-size: 0.8rem;
            background: transparent;
            color: #0e7490;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline:hover {
            background: #0ea5e9;
            color: #ffffff;
            transform: translateY(-2px);
        }

        /* ======================= QUESTION ======================= */
        .question {
            padding: 16px;
            background: rgba(240, 249, 255, 0.8);
            border-radius: 12px;
            border: 2px solid rgba(14, 165, 233, 0.3);
            margin-bottom: 16px;
        }

        .question-text {
            font-size: 0.95rem;
            margin-bottom: 12px;
            font-weight: 600;
            color: #0c4a6e;
            line-height: 1.5;
        }

        .options label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.85rem;
            margin-bottom: 8px;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
            border: 2px solid transparent;
        }

        .options label:hover {
            background: #f0f9ff;
            border-color: #0ea5e9;
        }

        .options input[type="radio"] {
            margin-top: 2px;
            flex-shrink: 0;
            cursor: pointer;
            width: 18px;
            height: 18px;
        }

        .options input[type="radio"]:checked+span {
            font-weight: 600;
            color: #0c4a6e;
        }

        /* ======================= BUTTON & STATUS ======================= */
        .quiz-actions {
            margin-top: 16px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        button {
            padding: 10px 20px;
            font-size: 0.9rem;
            border-radius: 999px;
            background: linear-gradient(90deg, #0ea5e9, #0284c7);
            border: none;
            color: white;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }

        button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(14, 165, 233, 0.4);
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #cbd5e1;
            box-shadow: none;
        }

        #statusText {
            margin-top: 12px;
            padding: 10px 14px;
            font-size: 0.85rem;
            background: rgba(240, 249, 255, 0.9);
            border-left: 4px solid #0ea5e9;
            border-radius: 8px;
            line-height: 1.5;
        }

        /* ======================= QUIZ MODE ======================= */
        /* Saat quiz-mode: header hilang, timer muncul */
        body.quiz-mode .header-container {
            display: none;
        }

        body.quiz-mode .timer-wrapper {
            display: block !important;
        }

        /* ======================= RESPONSIVE ======================= */
        /* Tablet Landscape */
        @media (max-width: 1024px) and (orientation: landscape) {
            .header-photos {
                gap: 100px;
            }

            .header-photo img {
                /*max-width: 100px;*/
            }

            .header-logo img {
                max-width: 140px;
            }

            .title-block h1 {
                font-size: 1.1rem;
            }

            .title-block .subtitle {
                font-size: 0.75rem;
            }

            .glass {
                padding: 16px;
            }
        }

        /* Mobile Portrait */
        @media (max-width: 768px) and (orientation: portrait) {
            .header-container {
                padding: 10px 12px 6px;
            }

            .header-photos {
                flex-direction: column;
                gap: 10px;
            }

            .header-photo img,
            .header-logo img {
                /*max-width: 120px;*/
            }

            .title-block h1 {
                font-size: 1rem;
            }

            .title-block .subtitle {
                font-size: 0.75rem;
            }

            .content-wrapper {
                padding: 12px 0;
            }

            .glass {
                padding: 16px;
            }

            .quiz-header {
                flex-direction: column;
                align-items: stretch;
            }

            .score-info {
                text-align: center;
            }

            .quiz-actions {
                flex-direction: column;
            }

            button {
                width: 100%;
            }
        }

        /* Small Mobile */
        @media (max-width: 480px) {
            .header-container {
                padding: 8px 10px 4px;
            }

            .title-block h1 {
                font-size: 0.9rem;
            }

            .title-block .subtitle {
                font-size: 0.7rem;
            }

            .glass {
                padding: 14px;
                border-radius: 16px;
            }

            .question {
                padding: 12px;
            }

            .question-text {
                font-size: 0.85rem;
            }

            .options label {
                font-size: 0.8rem;
                padding: 8px;
            }
        }

        /* Landscape khusus untuk tablet / layar pendek */
        @media (max-height: 600px) and (orientation: landscape) {
            .header-container {
                padding: 6px 12px 4px;
            }

            .header-photos {
                margin-bottom: 6px;
            }

            .header-photo img {
                /*max-width: 80px;*/
            }

            .header-logo img {
                max-width: 100px;
            }

            .title-block h1 {
                font-size: 0.95rem;
                margin-bottom: 2px;
            }

            .title-block .subtitle {
                font-size: 0.7rem;
            }

            .content-wrapper {
                padding: 8px 0;
            }

            .glass {
                padding: 12px;
            }

            .question {
                padding: 10px;
                margin-bottom: 10px;
            }

            button {
                padding: 6px 14px;
                font-size: 0.8rem;
            }
        }


        /* Sembunyikan foto Bupati & Camat saat HP portrait */
        @media (orientation: portrait) {
            .header-photo {
                display: none;
            }

            .header-photos {
                gap: 0;
                /* karena cuma logo, gap tidak perlu besar */
                margin-bottom: 8px;
            }

            .header-logo img {
                max-width: 120px;
                /* boleh dibesarkan sedikit kalau mau */
            }
        }
    </style>
</head>

<body>
    <div class="page-wrapper">
        <!-- HEADER (akan di-hide saat quiz-mode) -->
        <div class="header-container">
            <div class="header-photos">
                <div class="header-photo">
                    <img src="wkdh.png" alt="Bupati" />
                </div>
                <div class="header-logo">
                    <img src="logo.png" alt="Logo Hari Jadi Tanah Laut" />
                </div>
                <div class="header-photo">
                    <img src="camat.png" alt="Camat Pelaihari" />
                </div>
            </div>

            <div class="title-block">
                <h1>Kuis Hari Jadi Kabupaten Tanah Laut ke-60</h1>
                <div class="subtitle">
                    "Bagawi Sabarataan Tanah Laut, Simpun Bakamajuan"
                </div>
            </div>
        </div>

        <!-- TIMER -->
        <div id="timerWrapper" class="timer-wrapper">
            <div class="timer-badge" id="timerBadge">
                <span class="timer-label">⏱️ Sisa waktu:</span>
                <span id="timerText">01:00</span>
            </div>
        </div>

        <!-- CONTENT AREA -->
        <div class="content-wrapper">
            <!-- AUDIO -->
            <audio id="startSound" src="start.mp3"></audio>
            <audio id="tickSound" src="tick.mp3" loop></audio>
            <audio id="warningSound" src="warning.mp3"></audio>
            <audio id="timeUpSound" src="timeup.mp3"></audio>
            <audio id="winSound" src="win.mp3"></audio>

            <!-- START CARD -->
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

            <!-- KUIS: 1 SOAL PER HALAMAN -->
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
                <div id="statusText"></div>
                <div class="quiz-actions">
                    <button id="prevBtn" type="button" class="btn-outline">
                        Pertanyaan Sebelumnya
                    </button>
                    <button id="nextBtn" type="button">
                        Pertanyaan Selanjutnya
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ===========================
        // BANK SOAL -> acak TOTAL_QUESTIONS
        // ===========================
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

        // ===========================
        // ELEMENT & STATE
        // ===========================
        const quizContainer = document.getElementById("quizContainer");

        const startCard = document.getElementById("startCard");
        const startBtn = document.getElementById("startBtn");
        const quizCard = document.getElementById("quizCard");
        const statusText = document.getElementById("statusText");
        const scoreValueEl = document.getElementById("scoreValue");
        const timerTextEl = document.getElementById("timerText");
        const timerBadge = document.getElementById("timerBadge");
        const timerWrapper = document.getElementById("timerWrapper");
        const backToStartBtn = document.getElementById("backToStartBtn");
        const prevBtn = document.getElementById("prevBtn");
        const nextBtn = document.getElementById("nextBtn");

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

        // state soal per halaman
        let currentQuestionIndex = 0;
        let answers = new Array(QUESTIONS.length).fill(null);

        // ===========================
        // TIMER
        // ===========================
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

        // ===========================
        // IDLE TIMER
        // ===========================
        function clearIdleTimer() {
            if (idleTimer) {
                clearTimeout(idleTimer);
                idleTimer = null;
            }
        }

        function resetIdleTimer() {
            clearIdleTimer();
            if (quizCard.style.display !== "none") {
                idleTimer = setTimeout(() => {
                    backToStart();
                }, IDLE_LIMIT);
            }
        }

        // ===========================
        // RENDER 1 SOAL
        // ===========================
        function renderQuestion(index) {
            const q = QUESTIONS[index];
            if (!q) return;

            const storedAnswer = answers[index];

            quizContainer.innerHTML = `
                <div class="question">
                    <div class="question-text">
                        ${index + 1}. ${q.text}
                    </div>
                    <div class="options">
                        ${q.options.map((opt, idx) => `
                            <label>
                                <input type="radio"
                                       name="q${index}"
                                       value="${idx}"
                                       ${storedAnswer === idx ? "checked" : ""}>
                                <span>${opt}</span>
                            </label>
                        `).join("")}
                    </div>
                </div>
            `;

            const radios = quizContainer.querySelectorAll('input[name="q' + index + '"]');

            radios.forEach(r => {
                r.addEventListener("change", (e) => {
                    answers[index] = parseInt(e.target.value, 10);
                    updateNavButtons();
                });
            });

            updateNavButtons();
        }

        function updateNavButtons() {
            prevBtn.disabled = (currentQuestionIndex === 0);

            const currentAnswer = answers[currentQuestionIndex];
            const alreadyAnswered = (currentAnswer !== null && currentAnswer !== undefined);

            if (currentQuestionIndex === QUESTIONS.length - 1) {
                nextBtn.textContent = "Kumpulkan Jawaban";
            } else {
                nextBtn.textContent = "Pertanyaan Selanjutnya";
            }

            nextBtn.disabled = !alreadyAnswered || quizFinished;
        }

        // ===========================
        // LOGIKA FINISH
        // ===========================
        function disableQuizInputs() {
            const radios = quizContainer.querySelectorAll('input[type="radio"]');
            radios.forEach((r) => (r.disabled = true));
            prevBtn.disabled = true;
            nextBtn.disabled = true;
        }

        function finishQuiz(fromTimer = false) {
            if (quizFinished) return;

            let score = 0;
            let answered = 0;

            for (let i = 0; i < QUESTIONS.length; i++) {
                const ans = answers[i];
                if (ans !== null && ans !== undefined) {
                    answered++;
                    if (ans === QUESTIONS[i].correctIndex) {
                        score += POINT_PER_QUESTION;
                    }
                }
            }

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

        // ===========================
        // NAVIGASI (KEMBALI / LANJUT)
        // ===========================
        prevBtn.addEventListener("click", () => {
            if (currentQuestionIndex > 0 && !quizFinished) {
                currentQuestionIndex--;
                renderQuestion(currentQuestionIndex);
                resetIdleTimer();
            }
        });

        nextBtn.addEventListener("click", () => {
            if (quizFinished) return;

            const currentAnswer = answers[currentQuestionIndex];
            if (currentAnswer === null || currentAnswer === undefined) {
                statusText.textContent = "Silakan pilih jawaban dulu sebelum lanjut.";
                return;
            }

            if (currentQuestionIndex < QUESTIONS.length - 1) {
                currentQuestionIndex++;
                renderQuestion(currentQuestionIndex);
                statusText.textContent = "";
                resetIdleTimer();
            } else {
                finishQuiz(false);
                resetIdleTimer();
            }
        });

        // ===========================
        // KEMBALI KE MENU AWAL
        // ===========================
        function backToStart() {
            document.body.classList.remove("quiz-mode");

            stopTimerAndSound();
            clearIdleTimer();

            quizFinished = false;
            timeLeft = QUIZ_DURATION;
            updateTimerDisplay();
            timerBadge.classList.remove("timer-warning");

            answers = new Array(QUESTIONS.length).fill(null);
            currentQuestionIndex = 0;
            quizContainer.innerHTML = "";
            statusText.textContent = "";

            quizCard.style.display = "none";
            timerWrapper.style.display = "none";
            startCard.style.display = "block";
        }

        backToStartBtn.addEventListener("click", () => {
            backToStart();
        });

        // ===========================
        // START FLOW
        // ===========================
        function startQuiz() {
            if (QUESTIONS.length === 0) {
                statusText.textContent = "Belum ada soal aktif. Hubungi petugas.";
                return;
            }

            document.body.classList.add("quiz-mode");

            quizFinished = false;
            scoreValueEl.textContent = "0";
            statusText.textContent = "";

            answers = new Array(QUESTIONS.length).fill(null);
            currentQuestionIndex = 0;

            startCard.style.display = "none";
            quizCard.style.display = "block";
            timerWrapper.style.display = "block";
            updateTimerDisplay();

            renderQuestion(currentQuestionIndex);

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

        ["click", "keydown", "mousemove", "scroll", "touchstart"].forEach(
            (evt) => {
                document.addEventListener(evt, resetIdleTimer);
            }
        );

        updateTimerDisplay();
    </script>
</body>

</html>