<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Soporte de características del módulo.
 */
function visorpdf_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return false; // MVP sin backup específico.
        default:
            return null;
    }
}

/**
 * Extrae el fileId desde una URL de Drive o devuelve el mismo string si ya es un ID.
 *
 * Ejemplos aceptados:
 * - https://drive.google.com/file/d/1AbCdEfGhIjKl/view?usp=sharing
 * - https://drive.google.com/open?id=1AbCdEfGhIjKl
 * - 1AbCdEfGhIjKl (solo ID)
 */
function visorpdf_extract_fileid(string $input): string {
    $input = trim($input);

    // Si parece una URL.
    if (preg_match('#^https?://#i', $input)) {
        // Patrón /file/d/FILEID/
        if (preg_match('#/file/d/([^/]+)#', $input, $matches)) {
            return $matches[1];
        }

        // Patrón ?id=FILEID
        if (preg_match('#[?&]id=([^&]+)#', $input, $matches)) {
            return $matches[1];
        }
    }

    // Por defecto devolvemos el string tal cual (asumimos que ya es un ID).
    return $input;
}

/**
 * Crea una instancia nueva de visorpdf.
 */
function visorpdf_add_instance(stdClass $data, mod_visorpdf_mod_form $mform = null) {
    global $DB, $USER;

    $data->timecreated  = time();
    $data->timemodified = $data->timecreated;

    // Normalizar fileid y embedurl.
    $fileid        = visorpdf_extract_fileid($data->driveurl);
    $data->fileid  = $fileid;
    $data->embedurl = "https://drive.google.com/file/d/{$fileid}/preview";

    // Altura por defecto si no viene.
    if (empty($data->height)) {
        $data->height = 600;
    }

    // Procesar intro estándar de Moodle.
    if (!isset($data->intro)) {
        $data->intro = '';
    }
    if (!isset($data->introformat)) {
        $data->introformat = FORMAT_HTML;
    }

    return $DB->insert_record('visorpdf', $data);
}

/**
 * Actualiza una instancia existente.
 */
function visorpdf_update_instance(stdClass $data, mod_visorpdf_mod_form $mform = null) {
    global $DB;

    $data->id           = $data->instance;
    $data->timemodified = time();

    // Normalizar fileid y embedurl.
    $fileid        = visorpdf_extract_fileid($data->driveurl);
    $data->fileid  = $fileid;
    $data->embedurl = "https://drive.google.com/file/d/{$fileid}/preview";

    if (empty($data->height)) {
        $data->height = 600;
    }

    if (!isset($data->intro)) {
        $data->intro = '';
    }
    if (!isset($data->introformat)) {
        $data->introformat = FORMAT_HTML;
    }

    return $DB->update_record('visorpdf', $data);
}

/**
 * Elimina una instancia.
 */
function visorpdf_delete_instance($id) {
    global $DB;

    if (!$visorpdf = $DB->get_record('visorpdf', ['id' => $id])) {
        return false;
    }

    // No tenemos archivos físicos guardados, solo eliminamos el registro.
    $DB->delete_records('visorpdf', ['id' => $visorpdf->id]);

    return true;
}
