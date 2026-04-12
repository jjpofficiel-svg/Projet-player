<?php
// Connexion à la base de données
require_once 'includes/config_db.php';

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ────────────────────────────────────────────────
    // Fonctions utilitaires
    // ────────────────────────────────────────────────

    //─────────────────────── Recherche dossiers ───────────────────────────────── 
    function listAllSubdirectories($dir) {
        $subdirs = [];
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $subdirs[] = $path;
                $subdirs = array_merge($subdirs, listAllSubdirectories($path));
            }
        }
        return $subdirs;
    }
// !hsrjydtujkidkluol
    //─────────────────────── Recherche fichiers audio ─────────────────────────── 
    function scanMp3FilesFlat($dir) {
        return glob($dir . '/*.{m4a,mp3,opus}', GLOB_BRACE) ?: [];
    }

    // ────────────────────────────────────────────────
    // Logique principale
    // ────────────────────────────────────────────────

    // Initialisation de GetID3
    require_once 'includes/getid3/getid3.php';
    $getID3 = new getID3();
//───────────────────────────────────────────────── 
    $selectedDir = $_GET['dir'] ?? '../fichiers mp3';
    $songs = [];
//─────────────────────────────────────────────────

    if ($selectedDir) {
        $audioDir = rtrim($selectedDir, '/');

        if (!is_dir($audioDir)) {
            mkdir($audioDir, 0755, true);
        }

        $mp3Files = scanMp3FilesFlat($audioDir);

    // Ajout des nouveaux fichiers
    foreach ($mp3Files as $file) {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM songs WHERE audio_url = ?');
        $stmt->execute([$file]);
        if ($stmt->fetchColumn() == 0) {
            $filename = basename($file);
            $title   = pathinfo($filename, PATHINFO_FILENAME);

            // Extraction des métadonnées avec GetID3
            $fileInfo = $getID3->analyze($file);
            $artist = !empty($fileInfo['tags']['id3v2']['artist'][0]) ? $fileInfo['tags']['id3v2']['artist'][0] : 'Artiste inconnu';
            $album = !empty($fileInfo['tags']['id3v2']['album'][0]) ? $fileInfo['tags']['id3v2']['album'][0] : '';

            $stmt = $conn->prepare(
                'INSERT INTO songs (title, artist, audio_url, album) 
                    VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$title, $artist, $file, $album]);
        }
    }

        // Nettoyage des fichiers supprimés
        $stmt = $conn->query('SELECT audio_url FROM songs');
        $dbFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($dbFiles as $dbFile) {
            if (!file_exists($dbFile) || !in_array(realpath($dbFile), array_map('realpath', $mp3Files))) {
                $stmt = $conn->prepare('DELETE FROM songs WHERE audio_url = ?');
                $stmt->execute([$dbFile]);
            }
        }

        // Récupération des chansons du dossier courant
        $stmt = $conn->prepare('SELECT * FROM songs WHERE audio_url LIKE ? ORDER BY title ASC');
        $stmt->execute([rtrim($audioDir, '/') . '/%']);
        $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
//─────────────────────────────────────────────────── 
    $subdirs = listAllSubdirectories('../fichiers mp3');
//───────────────────────────────────────────────────
    } catch (PDOException $e) {
      die('Erreur de connexion : ' . $e->getMessage());
    }
?>
<!--═══════════════════════════════════════════════════════════════════
                                  HTML 
══════════════════════════════════════════════════════════════════════-->
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecteur Audio</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/css_player.css">
   

</head>
<body>
<?php //include 'includes/menuprincipal.php'; ?> 

<!-- ═══════════════════════════════════════════════════════════════════
     Notes de musique flottantes
══════════════════════════════════════════════════════════════════════ -->
<div class="music-note note-1">♪</div>
<div class="music-note note-2">♫</div>
<div class="music-note note-3">♪</div>
<div class="music-note note-4">♫</div>
<div class="music-note note-5">♪</div>
<div class="music-note note-6">♬</div>
<!-- Notes spécifiques zone haute du lecteur -->
<div class="music-note note-7">♪</div>
<div class="music-note note-8">♫</div>
<div class="music-note note-9">♬</div>
<div class="music-note note-10">♪</div>
<div center>

 <div class="container mt-5 py-4">
 <hr>
<!-- Bouton stylé --> 
<div style="display: flex; justify-content: flex-end;">
<a href="gestion_mp3.php" class="button"> 
<button center class="button">
  <div class="button-outer">
    <div class="button-inner">
      <span>Gestion</span>
    </div>
  </div>
</button>
</a>
</div>

<div class="audio-player position-relative"> 

<!-- ─────────────────────── Sélection dossier ────────────────────────────────── 
───────────────────────────────────────────────────────────────────────────── -->
        <form method="get" id="dirForm" class="mb-4">
            <label for="dir" class="form-label">Dossier audio</label>
            <select name="dir" id="dir" class="form-select song-select" onchange="document.getElementById('dirForm').submit()">
                <option value="audio" <?= (!isset($_GET['dir']) || $_GET['dir'] === 'audio') ? 'selected' : '' ?>>
                    audio (racine)
                </option>
                <?php foreach ($subdirs as $subdir): 
                    $label = htmlspecialchars(str_replace('../fichiers mp3/',' ', $subdir)); //──Remplace le nom du fichier mp3 + / par point + point
                    $sel = (isset($_GET['dir']) && $_GET['dir'] === $subdir) ? 'selected' : '';
                ?>
                    <option value="<?= $subdir ?>" <?= $sel ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </form>

<!-- ───────────────── Affichage titre ────────────────────────────────────────────  
─────────────────────────────────────────────────────────────────────────────── -->
  
        <h4 id="song-title" class="text-center mb-1 fw-light"></h4>

<!-- ───────────────── Affichage artiste─────────────────────────────────────────── 
─────────────────────────────────────────────────────────────────────────────── -->
        <p id="song-artist" class="text-center text-white-50 small mb-4"></p>

<!-- ────────────────── Sélection titre ────────────────────────────────────────────
──────────────────────────────────────────────────────────────────────────────── -->
   
        <select id="song-select" class="form-select song-select mb-4" onchange="playSong(this.value)">
            <?php if (empty($songs)): ?>
                <option>Aucune chanson disponible</option>
            <?php else: ?>
                <?php foreach ($songs as $i => $song): ?>
                    <option value="<?= $i ?>"><?= htmlspecialchars($song['title']) ?></option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
        <audio id="audio" preload="metadata"></audio>

<!-- ────────────────── Barre de progression ───────────────────────────────────────  
─────────────────────────────────────────────────────────────────────────────── -->

        <div class="progress-bar-container mb-3" id="progress-bar">
            <div class="progress" id="progress"></div>
        </div>
<!-- ─────────────────── Barre du temps ─────────────────────────────────────────────
───────────────────────────────────────────────────────────────────────────────── -->

        <div class="d-flex justify-content-between text-white-50 small mb-3">
            <span id="current-time">0:00</span>
            <span id="duration">0:00</span>
        </div>

 <!-- ─────────────────────────────Les boutons ──────────────────────────────────────
 ───────────────────────────────────────────────────────────────────────────────── -->
 
        <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
            <button type="button" class="btn-control" id="repeat-btn" aria-label="Répéter">
                <i class="fas fa-repeat"></i>
            </button>
            <button type="button" class="btn-control" id="shuffle-btn" aria-label="Aléatoire">
                <i class="fas fa-shuffle"></i>
            </button>
            <button type="button" class="btn-control" onclick="skip(-10)" aria-label="Reculer 10s">
                <i class="fas fa-backward"></i>
            </button>
            <button type="button" class="btn-control fs-3 px-4" id="play-pause" aria-label="Lecture/Pause">
                <i class="fas fa-play"></i>
            </button>
            <button type="button" class="btn-control" onclick="skip(10)" aria-label="Avancer 10s">
                <i class="fas fa-forward"></i>
            </button>
<!-- ──────────────────────────────── Volume ──────────────────────────────────────
───────────────────────────────────────────────────────────────────────────────── -->
 
            <div class="d-flex align-items-center ms-3">
                <i class="fas fa-volume-up me-2"></i>
                <input type="range" class="form-range" style="width: 100px;" 
                       min="0" max="100" value="60" id="volume">
            </div>
<!-- ───────────────────────────────────────────────────────────────────────────────── -->   

        </div>
    </div><br><hr>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ────────────────────────────────────────────────
// Lecteur audio – JavaScript
// ────────────────────────────────────────────────

const audio       = document.getElementById('audio');
const playPause   = document.getElementById('play-pause');
const progressBar = document.getElementById('progress-bar');
const progress    = document.getElementById('progress');
const currentTime = document.getElementById('current-time');
const duration    = document.getElementById('duration');
const volume      = document.getElementById('volume');
const titleEl     = document.getElementById('song-title');
const artistEl    = document.getElementById('song-artist');
const songSelect  = document.getElementById('song-select');
const repeatBtn   = document.getElementById('repeat-btn');
const shuffleBtn  = document.getElementById('shuffle-btn');

const songs = <?= json_encode($songs) ?>;
let currentIndex   = 0;
let repeatMode     = false;
let shuffleMode    = false;

// Format time in MM:SS
function formatTime(seconds) {
    if (isNaN(seconds)) return '0:00';
    const minutes = Math.floor(seconds / 60);
    seconds = Math.floor(seconds % 60);
    return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
}

function playSong(idx) {
    if (!songs[idx]) return;
    currentIndex = Number(idx);
    
    const song = songs[currentIndex];
    audio.src = song.audio_url;
    titleEl.textContent  = song.title;
    artistEl.textContent = song.artist || 'Artiste inconnu';
    
    audio.play().catch(e => console.warn("Play error:", e));
    playPause.innerHTML = '<i class="fas fa-pause"></i>';
    songSelect.value = currentIndex;
}

function playNext() {
    currentIndex = (currentIndex + 1) % songs.length;
    playSong(currentIndex);
}

function playRandom() {
    currentIndex = Math.floor(Math.random() * songs.length);
    playSong(currentIndex);
}

function skip(seconds) {
    audio.currentTime = Math.max(0, Math.min(audio.duration, audio.currentTime + seconds));
}

// ─── Événements ──────────────────────────────────────

playPause.onclick = () => {
    if (audio.paused) {
        audio.play().catch(e => console.warn(e));
        playPause.innerHTML = '<i class="fas fa-pause"></i>';
    } else {
        audio.pause();
        playPause.innerHTML = '<i class="fas fa-play"></i>';
    }
};

audio.onloadedmetadata = () => {
    duration.textContent = formatTime(audio.duration);
};

audio.ontimeupdate = () => {
    if (!audio.duration) return;
    const pct = (audio.currentTime / audio.duration) * 100;
    progress.style.width = pct + '%';
    currentTime.textContent = formatTime(audio.currentTime);
};

audio.onended = () => {
    if (repeatMode) {
        audio.currentTime = 0;
        audio.play();
    } else if (shuffleMode) {
        playRandom();
    } else {
        playNext();
    }
};

progressBar.onclick = e => {
    const rect = progressBar.getBoundingClientRect();
    const pos = (e.clientX - rect.left) / rect.width;
    audio.currentTime = pos * audio.duration;
};

volume.oninput = () => audio.volume = volume.value / 100;

repeatBtn.onclick = () => {
    repeatMode = !repeatMode;
    repeatBtn.classList.toggle('active', repeatMode);
    if (repeatMode) shuffleMode = false;
    shuffleBtn.classList.remove('active');
};

shuffleBtn.onclick = () => {
    shuffleMode = !shuffleMode;
    shuffleBtn.classList.toggle('active', shuffleMode);
    if (shuffleMode) {
        repeatMode = false;
        repeatBtn.classList.remove('active');
    }
};

// Initialisation
audio.volume = volume.value / 100;
if (songs.length > 0) {
    playSong(0);
}
</script>
</body>
</html>