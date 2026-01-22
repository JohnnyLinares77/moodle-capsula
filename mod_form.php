<?php
require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_visorpdf_mod_form extends moodleform_mod {

    public function definition() {
        $mform = $this->_form;

        // Sección general.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Nombre de la actividad.
        $mform->addElement('text', 'name', get_string('modulename', 'mod_visorpdf'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Descripción estándar (intro + introformat).
        $this->standard_intro_elements();

        // Campo para URL o ID de archivo de Drive.
        $mform->addElement('text', 'driveurl', get_string('driveurl', 'mod_visorpdf'), ['size' => 80]);
        $mform->setType('driveurl', PARAM_RAW); // Permitimos URL completa.
        $mform->addRule('driveurl', null, 'required', null, 'client');
        $mform->addHelpButton('driveurl', 'driveurl', 'mod_visorpdf');

        // *** OJO: ya no mostramos 'height' (lo eliminamos, ver paso 2). ***

        // Elementos estándar de configuración de módulo (visible, grupos, etc.).
        $this->standard_coursemodule_elements();

        // Botones de acción (guardar / cancelar).
        $this->add_action_buttons();
    }

    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        // Cuando editas la actividad, aquí llega el registro de la BD como $this->current.
        if (!empty($this->current->id)) {
            $defaultvalues['driveurl'] = $this->current->driveurl ?? '';
        }
    }
}
