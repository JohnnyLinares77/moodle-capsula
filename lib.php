<?php
// Función para servir los archivos (Pluginfile)
function capsula_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $DB;
    if ($context->contextlevel != CONTEXT_MODULE) return false;
    require_login($course, false, $cm);

    // Solo permitimos nuestras áreas de archivos
    if (!in_array($filearea, array('video', 'pdf'))) return false;

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = '/' . implode('/', $args) . '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_capsula', $filearea, 0, $filepath, $filename);

    if (!$file) return false;

    // Impedir descarga forzada (queremos que se vea en el visor)
    send_stored_file($file, null, 0, false, $options);
}

// Funciones para guardar/actualizar la instancia
function capsula_add_instance($capsula) {
    global $DB;
    $capsula->timemodified = time();
    $capsula->id = $DB->insert_record('capsula', $capsula);
    capsula_set_files($capsula);
    return $capsula->id;
}

function capsula_update_instance($capsula) {
    global $DB;
    $capsula->timemodified = time();
    $capsula->id = $capsula->instance;
    $DB->update_record('capsula', $capsula);
    capsula_set_files($capsula);
    return true;
}

function capsula_delete_instance($id) {
    global $DB;
    if (!$capsula = $DB->get_record('capsula', array('id' => $id))) {
        return false;
    }
    $DB->delete_records('capsula', array('id' => $capsula->id));
    return true;
}

function capsula_set_files($capsula) {
    $context = context_module::instance($capsula->coursemodule);
    file_save_draft_area_files($capsula->video_file, $context->id, 'mod_capsula', 'video', 0);
    file_save_draft_area_files($capsula->pdf_file, $context->id, 'mod_capsula', 'pdf', 0);
}

/**
 * Supports the mod_capsula module.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function capsula_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:           return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        default: return null;
    }
}
