<?php
require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('capsula', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$capsula = $DB->get_record('capsula', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Trigger module viewed event.
$event = \mod_capsula\event\course_module_viewed::create(array(
    'objectid' => $capsula->id,
    'context' => $context,
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('capsula', $capsula);
$event->trigger();

// Print header
$PAGE->set_url('/mod/capsula/view.php', array('id' => $id));
$PAGE->set_title(format_string($capsula->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($capsula->name));

if ($capsula->intro) {
    echo $OUTPUT->box(format_module_intro('capsula', $capsula, $cm->id), 'generalbox mod_introbox', 'intro');
}

$fs = get_file_storage();

// 1. Mostrar VIDEO (si aplica)
if ($capsula->showmode == 0 || $capsula->showmode == 1) {
    $video_files = $fs->get_area_files($context->id, 'mod_capsula', 'video', 0, 'sortorder, itemid, filepath, filename', false);
    if ($video_files) {
        $video = reset($video_files);
        $vurl = moodle_url::make_pluginfile_url($context->id, 'mod_capsula', 'video', 0, '/', $video->get_filename());
        echo "<div style='text-align:center; margin-bottom:30px;'>
                <video width='85%' height='auto' controls controlsList='nodownload' oncontextmenu='return false;'>
                    <source src='$vurl' type='".$video->get_mimetype()."'>
                    Tu navegador no soporta la etiqueta de video.
                </video>
              </div>";
    }
}

// 2. Mostrar PDF con PDF.js y Marca de Agua (si aplica)
if ($capsula->showmode == 0 || $capsula->showmode == 2) {
    $pdf_files = $fs->get_area_files($context->id, 'mod_capsula', 'pdf', 0, 'sortorder, itemid, filepath, filename', false);
    if ($pdf_files) {
        $pdf = reset($pdf_files);
        $purl = moodle_url::make_pluginfile_url($context->id, 'mod_capsula', 'pdf', 0, '/', $pdf->get_filename());
        ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
        <style>
            .capsula-container {
                width: 85%;
                margin: 0 auto;
                background-color: #202124; /* Google Drive dark gray */
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.3);
                overflow: hidden;
            }
            #pdf-container { 
                position: relative; 
                height: 85vh; 
                overflow: auto; 
                background-color: #525659; /* Inner gray */
                display: flex;
                flex-direction: column;
                align-items: center;
                padding-top: 20px;
            }
            .watermark-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                min-height: 100%; /* Ensure it grows with content */
                pointer-events: none;
                z-index: 10;
                overflow: hidden;
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                align-content: flex-start;
            }
            .watermark-text {
                color: rgba(255, 255, 255, 0.12);
                font-size: 18px;
                font-family: Arial, sans-serif;
                transform: rotate(-45deg);
                margin: 40px;
                white-space: nowrap;
                user-select: none;
                font-weight: bold;
            }
            canvas { 
                display: block; 
                margin: 0 auto 20px auto; 
                box-shadow: 0 2px 5px rgba(0,0,0,0.2); 
                max-width: 95%; /* Responsive width inside container */
                height: auto !important; /* Maintain aspect ratio */
            }
        </style>
        
        <div class="capsula-container">
            <div id="pdf-container" oncontextmenu="return false;">
                <div class="watermark-overlay">
                    <?php 
                        // Generar MUCHAS marcas de agua para asegurar cobertura
                        $watermark_text = $USER->username . " - " . date('d/m/Y');
                        for($i=0; $i<800; $i++) {
                            echo "<div class='watermark-text'>" . $watermark_text . "</div>"; 
                        }
                    ?>
                </div>
                <div id="pdf-render"></div>
            </div>
        </div>

        <script>
            // Deshabilitar click derecho y teclas de guardar
            document.addEventListener('contextmenu', event => event.preventDefault());
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'p' || e.key === 'u')) {
                    e.preventDefault();
                }
            });

            const pdfjsLib = window['pdfjs-dist/build/pdf'];
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            
            const url = '<?php echo $purl; ?>';
            
            pdfjsLib.getDocument(url).promise.then(pdf => {
                const container = document.getElementById('pdf-render');
                const updateWatermarkHeight = () => {
                     const overlay = document.querySelector('.watermark-overlay');
                     overlay.style.height = container.scrollHeight + 'px';
                };

                for (let i = 1; i <= pdf.numPages; i++) {
                    pdf.getPage(i).then(page => {
                        // High quality scale
                        const scale = 2.0; 
                        const viewport = page.getViewport({ scale: scale });
                        
                        const canvas = document.createElement('canvas');
                        container.appendChild(canvas);
                        
                        const context = canvas.getContext('2d');
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;
                        
                        // Control display size via CSS, keeping high res canvas
                        // This logic is handled by max-width: 95% in CSS
                        
                        const renderContext = {
                            canvasContext: context,
                            viewport: viewport
                        };
                        return page.render(renderContext).promise;
                    }).then(() => {
                        // Update watermark container height after standard render
                        updateWatermarkHeight();
                    });
                }
            }).catch(error => {
                console.error('Error loading PDF:', error);
                document.getElementById('pdf-render').innerHTML = '<p style="color:white; padding:20px;">Error loading document.</p>';
            });
        </script>
        <?php
    }
}

echo $OUTPUT->footer();
