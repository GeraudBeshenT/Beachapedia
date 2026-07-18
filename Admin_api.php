<?php
// admin_api.php
// Point d'entrée AJAX pour la page admin.php. Toutes les actions passent par ici
// (JSON en entrée/sortie), sauf l'upload d'image qui utilise un formulaire
// multipart classique (POST + fichier).
ini_set('display_errors', 0);
error_reporting(0);

require_once 'config.php';
require_once 'admin_config.php';

header('Content-Type: application/json');

if (!admin_check_access()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;

try {
    switch ($action) {

        case 'list_classes':
            $type = $_GET['type'] ?? '';
            if (!admin_get_entity($type)) throw new Exception("Type inconnu.");
            echo json_encode(['success' => true, 'classes' => admin_list_classes($pdo, $type)]);
            break;

        case 'list_tids':
            $type  = $_GET['type'] ?? '';
            $class = $_GET['class'] ?? '';
            if (!admin_get_entity($type)) throw new Exception("Type inconnu.");
            echo json_encode(['success' => true, 'tids' => admin_list_tids($pdo, $type, $class)]);
            break;

        case 'list_troops':
            echo json_encode(['success' => true, 'troops' => admin_list_troop_names($pdo)]);
            break;

        case 'get_data':
            $type = $_GET['type'] ?? '';
            $tid  = $_GET['tid'] ?? '';
            if (!admin_get_entity($type)) throw new Exception("Type inconnu.");
            if ($tid === '') throw new Exception("TID manquant.");

            $entity  = admin_get_entity($type);
            $id_row  = admin_get_id_row($pdo, $type, $tid);
            $levels  = admin_get_levels($pdo, $type, $tid);
            $label   = admin_get_tid_label($pdo, $tid);

            echo json_encode([
                'success'    => true,
                'entity'     => [
                    'label'      => $entity['label'],
                    'id_fields'  => $entity['id_fields'],
                    'fields'     => $entity['fields'],
                    'level_col'  => $entity['level_col'],
                    'image'      => $entity['image'],
                ],
                'id_row'   => $id_row,
                'levels'   => $levels,
                'tid_label'=> $label,
            ]);
            break;

        case 'get_officer_abilities':
            $tid = $_GET['tid'] ?? '';
            if ($tid === '') throw new Exception("TID manquant.");
            echo json_encode(['success' => true, 'abilities' => admin_get_officer_abilities($pdo, $tid)]);
            break;

        case 'save_officer_ability':
            $ability_tid = $_POST['ability_tid'] ?? '';
            $label       = $_POST['label'] ?? '';
            $icon        = $_POST['icon'] ?? '';
            admin_save_officer_ability($pdo, $ability_tid, $label, $icon);
            echo json_encode(['success' => true, 'message' => 'Capacité mise à jour.']);
            break;

        case 'get_officer_ability_levels':
            $ability_tid = $_GET['ability_tid'] ?? '';
            if ($ability_tid === '') throw new Exception("TID de capacité manquant.");
            echo json_encode([
                'success' => true,
                'fields'  => $GLOBALS['OFFICER_ABILITY_FIELDS'],
                'levels'  => admin_get_officer_ability_levels($pdo, $ability_tid),
            ]);
            break;

        case 'save_officer_ability_level':
            $ability_tid    = $_POST['ability_tid'] ?? '';
            $data           = json_decode($_POST['data'] ?? '{}', true) ?: [];
            $original_level = isset($_POST['original_level']) && $_POST['original_level'] !== ''
                ? $_POST['original_level'] : null;
            admin_save_officer_ability_level($pdo, $ability_tid, $data, $original_level);
            echo json_encode(['success' => true, 'message' => 'Niveau enregistré.']);
            break;

        case 'delete_officer_ability_level':
            $ability_tid = $_POST['ability_tid'] ?? '';
            $level       = $_POST['level'] ?? '';
            if ($ability_tid === '' || $level === '') throw new Exception("Paramètres manquants.");
            admin_delete_officer_ability_level($pdo, $ability_tid, $level);
            echo json_encode(['success' => true, 'message' => 'Niveau supprimé.']);
            break;

        case 'save_level':
            $type  = $_POST['type'] ?? '';
            $tid   = $_POST['tid'] ?? '';
            $data  = json_decode($_POST['data'] ?? '{}', true) ?: [];
            $original_level = isset($_POST['original_level']) && $_POST['original_level'] !== ''
                ? $_POST['original_level'] : null;

            if (!admin_get_entity($type)) throw new Exception("Type inconnu.");
            if ($tid === '') throw new Exception("TID manquant.");

            admin_save_level($pdo, $type, $tid, $data, $original_level);
            echo json_encode(['success' => true, 'message' => 'Niveau enregistré.']);
            break;

        case 'delete_level':
            $type  = $_POST['type'] ?? '';
            $tid   = $_POST['tid'] ?? '';
            $level = $_POST['level'] ?? '';
            if (!admin_get_entity($type)) throw new Exception("Type inconnu.");
            if ($tid === '' || $level === '') throw new Exception("Paramètres manquants.");

            admin_delete_level($pdo, $type, $tid, $level);
            echo json_encode(['success' => true, 'message' => 'Niveau supprimé.']);
            break;

        case 'save_id_row':
            $type = $_POST['type'] ?? '';
            $tid  = $_POST['tid'] ?? '';
            $data = json_decode($_POST['data'] ?? '{}', true) ?: [];
            if (!admin_get_entity($type)) throw new Exception("Type inconnu.");
            if ($tid === '') throw new Exception("TID manquant.");

            admin_save_id_row($pdo, $type, $tid, $data);
            echo json_encode(['success' => true, 'message' => 'Fiche mise à jour.']);
            break;

        case 'upload_image':
            $type  = $_POST['type'] ?? '';
            $tid   = $_POST['tid'] ?? '';
            $level = ($_POST['level'] ?? '') !== '' ? $_POST['level'] : null;

            if (!admin_get_entity($type)) throw new Exception("Type inconnu.");
            if ($tid === '') throw new Exception("TID manquant.");
            if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Aucun fichier valide reçu.");
            }

            // Sécurité : on n'accepte que des images, et on ignore totalement le nom
            // de fichier envoyé par le navigateur — le nom final est TOUJOURS recalculé
            // côté serveur à partir de ExportName / IconExportName (voir admin_resolve_image_path),
            // ce qui empêche l'écriture d'un fichier hors du dossier images/ (path traversal).
            $tmp_path = $_FILES['image']['tmp_name'];
            $info = @getimagesize($tmp_path);
            if ($info === false) {
                throw new Exception("Le fichier envoyé n'est pas une image valide.");
            }

            $target = admin_resolve_image_path($pdo, $type, $tid, $level);

            if (!is_dir($target['dir'])) {
                if (!mkdir($target['dir'], 0755, true)) {
                    throw new Exception("Impossible de créer le dossier de destination.");
                }
            }

            if (!move_uploaded_file($tmp_path, $target['abs_path'])) {
                throw new Exception("Échec de l'enregistrement du fichier sur le serveur.");
            }

            echo json_encode([
                'success'  => true,
                'message'  => 'Image envoyée.',
                'path'     => $target['dir'] . '/' . $target['filename'],
            ]);
            break;

        case 'add_character':
            $tid = admin_add_character($pdo, [
                'tid'             => $_POST['tid'] ?? '',
                'class'           => $_POST['class'] ?? '',
                'hq_unlock'       => $_POST['hq_unlock'] ?? 0,
                'icon'            => $_POST['icon'] ?? '',
                'officer_troop'   => $_POST['officer_troop'] ?? '',
                'officer_rank'    => $_POST['officer_rank'] ?? '',
                'officer_ability' => $_POST['officer_ability'] ?? '',
                'active_icon'     => $_POST['active_icon'] ?? '',
            ]);
            echo json_encode(['success' => true, 'message' => 'Personnage ajouté.', 'tid' => $tid]);
            break;

        default:
            throw new Exception("Action inconnue.");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}