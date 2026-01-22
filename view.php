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
            #pdf-container { position: relative; background: #333; height: 800px; overflow: auto; border: 1px solid #ccc; }
            .watermark { position: absolute; top: 0; left: 0; width:100%; height:100%; pointer-events:none; z-index:100; opacity:0.15; 
                          font-size:24px; color:rgba(255, 0, 0, 0.5); transform:rotate(-45deg); display:flex; flex-wrap:wrap; justify-content: space-around; align-content: space-around; overflow: hidden;}
            .watermark span { margin: 50px; white-space: nowrap; transform: rotate(-45deg); }
            canvas { display: block; margin: 0 auto 10px auto; box-shadow: 0 0 10px rgba(0,0,0,0.5); }
        </style>
        <div id="pdf-container" oncontextmenu="return false;">
            <div class="watermark">
                <?php 
                    // Generar marca de agua repetida
                    $watermark_text = $USER->username . " - " . date('d/m/Y H:i');
                    for($i=0; $i<50; $i++) echo "<span>" . $watermark_text . "</span>"; 
                ?>
            </div>
            <div id="pdf-render"></div>
        </div>
        <script>
            // Deshabilitar click derecho y teclas de guardar
            document.addEventListener('contextmenu', event => event.preventDefault());
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'p')) {
                    e.preventDefault();
                }
            });

            const pdfjsLib = window['pdfjs-dist/build/pdf'];
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            
            const url = '<?php echo $purl; ?>';
            
            pdfjsLib.getDocument(url).promise.then(pdf => {
                const container = document.getElementById('pdf-render');
                for (let i = 1; i <= pdf.numPages; i++) {
                    pdf.getPage(i).then(page => {
                        const viewport = page.getViewport({ scale: 1.5 });
                        const canvas = document.createElement('canvas');
                        container.appendChild(canvas);
                        const context = canvas.getContext('2d');
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;
                        
                        const renderContext = {
                            canvasContext: context,
                            viewport: viewport
                        };
                        page.render(renderContext);
                    });
                }
            }).catch(error => {
                console.error('Error loading PDF:', error);
                document.getElementById('pdf-render').innerText = 'Error loading PDF document.';
            });
        </script>
        <?php
    }
}

echo $OUTPUT->footer();
