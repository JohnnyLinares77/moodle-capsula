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

// NOTE: Content Description (Intro) is skipped to avoid potential duplication issues as requested.

$fs = get_file_storage();

// --- PREPARE USER DATA FOR OVERLAY ---
$user_info_primary = "{$USER->firstname} {$USER->lastname} - {$USER->idnumber} - {$USER->email}";
$user_info_secondary = date('d/m/Y') . " - " . date('H:i');

// --- START VIEWER CONTENT ---
?>

<!-- Dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
</script>

<!-- Styles -->
<style>
    :root {
        --viewer-bg: #202124;
        --viewer-inner-bg: #2d2e30;
        --scrollbar-thumb: #5f6368;
        --scrollbar-track: #202124;
    }

    /* Main Container 16:9 */
    .capsula-viewer-wrapper {
        position: relative;
        width: 85%;
        max-width: 1200px;
        margin: 20px auto;
        background-color: var(--viewer-bg);
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.5);
        aspect-ratio: 16 / 9;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    /* Toolbar (Zoom) */
    .capsula-toolbar {
        height: 48px;
        background-color: rgba(0,0,0,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
        color: white;
        z-index: 50;
        border-bottom: 1px solid #444;
    }

    .capsula-btn {
        background: transparent;
        border: none;
        color: #e8eaed;
        cursor: pointer;
        padding: 8px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
        font-size: 18px;
    }
    .capsula-btn:hover {
        background-color: rgba(255,255,255,0.1);
    }
    
    /* Content Area */
    .capsula-content {
        flex: 1;
        position: relative;
        overflow: hidden;
        background-color: var(--viewer-inner-bg);
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* Message Overlay */
    .capsula-info-overlay {
        position: absolute;
        top: 20px;
        left: 20px;
        z-index: 100;
        pointer-events: none;
        background-color: rgba(0, 0, 0, 0.65);
        padding: 10px 15px;
        border-radius: 6px;
        color: rgba(255, 255, 255, 0.9);
        font-family: 'Roboto', 'Segoe UI', Arial, sans-serif;
        font-size: 14px;
        line-height: 1.4;
        backdrop-filter: blur(4px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .capsula-info-line {
        display: block;
        white-space: nowrap;
    }
    .capsula-info-primary {
        font-weight: 500;
        color: #fff;
    }
    .capsula-info-secondary {
        font-size: 0.9em;
        color: #ccc;
        margin-top: 2px;
    }

    /* Video Player */
    .capsula-video {
        width: 100%;
        height: 100%;
        object-fit: contain; 
        outline: none;
    }

    /* PDF Scroll Area */
    #pdf-scroll-container {
        width: 100%;
        height: 100%;
        overflow-y: auto;
        overflow-x: auto;
        position: relative;
        scrollbar-width: thin;
        scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
    }
    
    /* Custom Scrollbar */
    #pdf-scroll-container::-webkit-scrollbar { width: 12px; height: 12px; }
    #pdf-scroll-container::-webkit-scrollbar-track { background: var(--scrollbar-track); }
    #pdf-scroll-container::-webkit-scrollbar-thumb {
        background-color: var(--scrollbar-thumb);
        border-radius: 6px;
        border: 3px solid var(--viewer-bg);
    }

    #pdf-render-layer {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 20px 0;
        min-height: 100%;
        position: relative;
        z-index: 1;
    }

    canvas { 
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        margin-bottom: 15px;
        /* Allow landscape to fit */
        max-width: 95%; 
        height: auto;
    }

    .no-select {
        user-select: none;
        -webkit-user-select: none;
    }
</style>

<?php
// --- 1. VIDEO RENDERING ---
if ($capsula->showmode == 0 || $capsula->showmode == 1) {
    $video_files = $fs->get_area_files($context->id, 'mod_capsula', 'video', 0, 'sortorder, itemid, filepath, filename', false);
    if ($video_files) {
        $video = reset($video_files);
        $vurl = moodle_url::make_pluginfile_url($context->id, 'mod_capsula', 'video', 0, '/', $video->get_filename());
        ?>
        
        <div class="capsula-viewer-wrapper no-select" oncontextmenu="return false;">
            <div class="capsula-info-overlay">
                <span class="capsula-info-line capsula-info-primary"><?php echo $user_info_primary; ?></span>
                <span class="capsula-info-line capsula-info-secondary"><?php echo $user_info_secondary; ?></span>
            </div>
            
            <div class="capsula-content">
                <video class="capsula-video" controls controlsList="nodownload" disablePictureInPicture>
                    <source src="<?php echo $vurl; ?>" type="<?php echo $video->get_mimetype(); ?>">
                    Tu navegador no soporta video HTML5.
                </video>
            </div>
        </div>
        
        <?php
    }
}

// --- 2. PDF RENDERING ---
if ($capsula->showmode == 0 || $capsula->showmode == 2) {
    $pdf_files = $fs->get_area_files($context->id, 'mod_capsula', 'pdf', 0, 'sortorder, itemid, filepath, filename', false);
    if ($pdf_files) {
        $pdf = reset($pdf_files);
        $purl = moodle_url::make_pluginfile_url($context->id, 'mod_capsula', 'pdf', 0, '/', $pdf->get_filename());
        ?>
        
        <div class="capsula-viewer-wrapper" id="capsula-pdf-wrapper">
            <!-- Toolbar -->
            <div class="capsula-toolbar no-select">
                 <button class="capsula-btn" onclick="zoomOut()" title="Zoom Out">➖</button>
                 <span id="zoom-level" style="font-family: monospace; min-width: 50px; text-align: center;">100%</span>
                 <button class="capsula-btn" onclick="zoomIn()" title="Zoom In">➕</button>
            </div>

            <div class="capsula-content" style="position: relative;">
                <div class="capsula-info-overlay">
                    <span class="capsula-info-line capsula-info-primary"><?php echo $user_info_primary; ?></span>
                    <span class="capsula-info-line capsula-info-secondary"><?php echo $user_info_secondary; ?></span>
                </div>

                <div id="pdf-scroll-container" oncontextmenu="return false;" class="no-select">
                     <div id="pdf-render-layer"></div>
                </div>
            </div>
        </div>

        <script>
            let pdfDoc = null;
            let currentScale = 1.0;
            const pdfUrl = '<?php echo $purl; ?>';

            // Security: Disable Context Menu & Shortcuts
            document.addEventListener('contextmenu', event => event.preventDefault());
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'p' || e.key === 'u')) {
                    e.preventDefault();
                }
            });

            // Load PDF
            pdfjsLib.getDocument(pdfUrl).promise.then(pdf => {
                pdfDoc = pdf;
                renderAllPages(currentScale);
            }).catch(err => {
                console.error("Error loading PDF: " + err);
                const container = document.getElementById('pdf-render-layer');
                if(container) container.innerHTML = "<div style='color:white; padding:20px;'>Error cargando documento.</div>";
            });

            function renderAllPages(scale) {
                const container = document.getElementById('pdf-render-layer');
                if(!container) return;
                
                container.innerHTML = '';
                const zoomLabel = document.getElementById('zoom-level');
                if(zoomLabel) zoomLabel.innerText = Math.round(scale * 100) + "%";

                const renderPage = (num) => {
                    if (num > pdfDoc.numPages) return;

                    pdfDoc.getPage(num).then(page => {
                        // High DPI Rendering: Render at 1.5x requested scale
                        const outputScale = scale * 1.5;
                        const viewport = page.getViewport({ scale: outputScale });

                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');
                        
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;
                        
                        // Visual Size (CSS)
                        // We set width ensuring it respects CSS max-width 95%
                        const displayWidth = viewport.width / 1.5;
                        canvas.style.width = displayWidth + "px";
                        // Remove height restriction to maintain aspect ratio
                        canvas.style.height = "auto"; 
                        canvas.style.maxWidth = "95%"; // Responsive constraint

                        container.appendChild(canvas);

                        const renderContext = {
                            canvasContext: context,
                            viewport: viewport
                        };
                        
                        page.render(renderContext).promise.then(() => {
                            renderPage(num + 1);
                        });
                    });
                };
                renderPage(1);
            }

            function zoomIn() {
                if (currentScale >= 3.0) return;
                currentScale += 0.25;
                renderAllPages(currentScale);
            }

            function zoomOut() {
                if (currentScale <= 0.5) return;
                currentScale -= 0.25;
                renderAllPages(currentScale);
            }
        </script>
        <?php
    }
}

echo $OUTPUT->footer();
