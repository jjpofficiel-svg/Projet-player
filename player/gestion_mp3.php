<?php
$root_dir = '../fichiers mp3';
if (!is_dir($root_dir)) {
    mkdir($root_dir, 0755, true);
}

$relative_path = isset($_GET['dir']) ? str_replace(['..', './'], '', $_GET['dir']) : '';
$relative_path = trim($relative_path, '/');
$current_full_path = $relative_path ? $root_dir . '/' . $relative_path : $root_dir;

$message = "";

// --- FONCTION DOSSIERS POUR DÉPLACEMENT ---
function get_all_folders($dir, $root, &$results = array())
{
    $items = array_diff(scandir($dir), array('.', '..'));
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            $rel = ltrim(str_replace($root, '', $path), '/');
            $results[] = $rel;
            get_all_folders($path, $root, $results);
        }
    }
    return $results;
}

// --- ACTIONS ---

// 1. Déplacement
if (isset($_POST['move_file']) && isset($_POST['dest_folder'])) {
    $file_to_move = basename($_POST['move_file']);
    $source = $current_full_path . '/' . $file_to_move;
    $dest_folder = $_POST['dest_folder'] === 'root' ? $root_dir : $root_dir . '/' . $_POST['dest_folder'];
    $destination = $dest_folder . '/' . $file_to_move;
    if (file_exists($source) && is_dir($dest_folder)) {
        if (rename($source, $destination)) {
            $message = "<div class='alert alert-success py-2'>Fichier déplacé.</div>";
        }
    }
}

// 2. Création de dossier
if (isset($_POST['new_folder_name'])) {
    $new_dir = $current_full_path . '/' . basename($_POST['new_folder_name']);
    if (!is_dir($new_dir)) {
        mkdir($new_dir, 0755);
        $message = "<div class='alert alert-success py-2'>Dossier créé.</div>";
    }
}

// 3. Upload
if (!empty($_FILES['files'])) {
    foreach ($_FILES['files']['name'] as $key => $name) {
        if ($_FILES['files']['error'][$key] == 0) {
            $raw_path = isset($_FILES['files']['full_path'][$key]) ? urldecode($_FILES['files']['full_path'][$key]) : $name;
            $clean_name = preg_replace('/^.*[:\/]Download\//i', '', $raw_path);
            $target_path = $current_full_path . '/' . ltrim($clean_name, '/');
            if (!is_dir(dirname($target_path))) {
                mkdir(dirname($target_path), 0755, true);
            }
            move_uploaded_file($_FILES['files']['tmp_name'][$key], $target_path);
        }
    }
    $message = "<div class='alert alert-success py-2'>Transfert terminé.</div>";
}

// 4. Suppression & 5. Renommage
if (isset($_GET['delete'])) {
    $target = $current_full_path . '/' . basename($_GET['delete']);
    if (is_file($target)) unlink($target);
    elseif (is_dir($target)) rmdir($target);
}
if (isset($_POST['old_name']) && isset($_POST['new_name'])) {
    $old = $current_full_path . '/' . basename($_POST['old_name']);
    $new = $current_full_path . '/' . basename($_POST['new_name']) . (is_dir($old) ? '' : '.mp3');
    if (file_exists($old)) rename($old, $new);
}

// Tri et Liste
$items = array_diff(scandir($current_full_path), array('.', '..'));
usort($items, function ($a, $b) use ($current_full_path) {
    $a_dir = is_dir($current_full_path . '/' . $a);
    $b_dir = is_dir($current_full_path . '/' . $b);
    if ($a_dir && !$b_dir) return -1;
    if (!$a_dir && $b_dir) return 1;
    return strnatcasecmp($a, $b);
});
$all_folders = get_all_folders($root_dir, $root_dir);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionnaire MP3</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        .file-clickable {
            cursor: pointer;
            color: #0d6efd;
            transition: 0.2s;
        }

        .file-clickable:hover {
            color: #004db3;
            text-decoration: underline;
        }

        .audio-player {
            height: 30px;
            width: 100%;
            max-width: 200px;
        }

        .breadcrumb-item a {
            text-decoration: none;
        }
    </style>
</head>

<body class="bg-light">

    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-center mb-3 bg-white p-3 rounded shadow-sm">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="?dir="><i class="bi bi-house-fill"></i></a></li>
                    <?php
                    $cumul = '';
                    foreach (explode('/', $relative_path) as $part) {
                        if (!$part) continue;
                        $cumul .= ($cumul ? '/' : '') . $part;
                        echo "<li class='breadcrumb-item'><a href='?dir=$cumul'>$part</a></li>";
                        // Note: urlencode n'est pas utilisé ici pour les liens de navigation afin de garder les URL lisibles, mais cela suppose que les noms de dossiers ne contiennent pas de caractères problématiques.
                    }
                    ?>
                </ol>
            </nav>
            <div class="btn-group">
                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#folderModal"><i class="bi bi-folder-plus"></i></button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="bi bi-cloud-arrow-up-fill"></i></button>
                <button class="btn btn-outline-secondary"><i class="bi bi-music-note2"></i><a href="index.php" class="text-decoration-none">♫ Lecteurs</a></button>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Nom</th>
                            <th class="d-none d-md-table-cell">Lecteur</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $idx = 0;
                        foreach ($items as $item):
                            $path = $current_full_path . '/' . $item;
                            $is_dir = is_dir($path);
                        ?>
                            <tr>
                                <td>
                                    <?php if ($is_dir): ?>
                                        <a href="?dir=<?php echo ($relative_path ? $relative_path . '/' : '') . urlencode($item); ?>" class="text-decoration-none fw-bold text-dark">
                                            <i class="bi bi-folder-fill text-warning me-2"></i><?php echo $item; ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="file-clickable" onclick="playSong(<?php echo $idx; ?>)">
                                            <i class="bi bi-music-note-beamed me-2"></i><?php echo preg_replace('/\.mp3$/i', '', $item); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if (!$is_dir): ?>
                                        <audio controls class="audio-player" id="audio-<?php echo $idx; ?>">
                                            <source src="<?php echo $path; ?>" type="audio/mpeg">
                                        </audio>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <?php if (!$is_dir): ?>
                                            <button class="btn btn-sm btn-outline-info" onclick="moveItem('<?php echo addslashes($item); ?>')"><i class="bi bi-arrows-move"></i></button>
                                            <audio id="audio-mobile-<?php echo $idx; ?>" class="d-md-none">
                                                <source src="<?php echo $path; ?>" type="audio/mpeg">
                                            </audio>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-primary" onclick="renameItem('<?php echo addslashes($item); ?>', <?php echo $is_dir ? 'true' : 'false'; ?>)"><i class="bi bi-pencil"></i></button>
                                        <a href="?dir=<?php echo urlencode($relative_path); ?>&delete=<?php echo urlencode($item); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php $idx++;
                        endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="moveModal" tabindex="-1">
        <div class="modal-dialog">
            <form action="" method="POST" class="modal-content">
                <div class="modal-header">
                    <h5>Déplacer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"><input type="hidden" name="move_file" id="move_file_input">
                    <p id="move_filename_display" class="fw-bold"></p><select name="dest_folder" class="form-select">
                        <option value="root">Racine</option><?php foreach ($all_folders as $f): ?><option value="<?php echo $f; ?>"><?php echo $f; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Confirmer</button></div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <form action="" method="POST" enctype="multipart/form-data" class="modal-content">
                <div class="modal-header">
                    <h5>Importer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Fichiers</label><input type="file" name="files[]" accept=".mp3" class="form-control" multiple></div>
                    <div class="mb-3"><label class="form-label">Dossier complet</label><input type="file" name="files[]" webkitdirectory directory class="form-control"></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary w-100">Envoyer</button></div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="folderModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <form action="" method="POST" class="modal-content">
                <div class="modal-header">
                    <h5>Nouveau dossier</h5>
                </div>
                <div class="modal-body"><input type="text" name="new_folder_name" class="form-control" required></div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Créer</button></div>
            </form>
        </div>
    </div>

    <script>
        function playSong(idx) {
            // On cherche le lecteur (soit celui visible desktop, soit celui invisible mobile)
            let player = document.getElementById('audio-' + idx) || document.getElementById('audio-mobile-' + idx);

            // Arrêter tous les autres
            document.querySelectorAll('audio').forEach(a => {
                if (a !== player) a.pause();
            });

            if (player.paused) player.play();
            else player.pause();
        }

        function moveItem(f) {
            document.getElementById('move_file_input').value = f;
            document.getElementById('move_filename_display').innerText = f;
            new bootstrap.Modal(document.getElementById('moveModal')).show();
        }

        function renameItem(o, d) {
            let n = prompt("Nouveau nom :", d ? o : o.replace('.mp3', ''));
            if (n && n !== o) {
                let f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="old_name" value="${o}"><input type="hidden" name="new_name" value="${n}">`;
                document.body.appendChild(f);
                f.submit();
            }
        }

        // Suite auto
        document.addEventListener("DOMContentLoaded", function() {
            let audios = document.querySelectorAll("audio");
            audios.forEach((a, i) => {
                a.addEventListener("ended", function() {
                    if (audios[i + 1]) {
                        audios[i + 1].play();
                        audios[i + 1].scrollIntoView({
                            behavior: "smooth",
                            block: "center"
                        });
                    }
                });
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>