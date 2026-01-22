<?php
require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('capsula', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$capsula = $DB->get_record('capsula', array('id' => $cm->instance), '*', MUST_EXIST);
$capsula->showmode = (int)$capsula->showmode; // Ensure integer type

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
    @import url('https://fonts.googleapis.com/css2?family=Ubuntu+Mono:wght@400;700&display=swap');

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

    /* Watermark Overlay (Repeated Pattern) */
    .capsula-watermark-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 100;
        pointer-events: none;
        overflow: hidden;
    }
    .capsula-watermark-overlay svg {
        width: 100%;
        height: 100%;
    }
    .wm-text {
        font-family: 'Ubuntu Mono', monospace; /* */
        font-weight: 600; /* */
        fill: rgba(150, 150, 150, 0.50); /* El color exacto que pediste */
        pointer-events: none;
        text-transform: uppercase;
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
        position: relative;
        z-index: 50; /* Ensure PDF is above background */
    }

    .no-select {
        user-select: none;
        -webkit-user-select: none;
    }
</style>

<?php
// --- PREPARE USER DATA FOR OVERLAY ---
$watermark_line_1 = "{$USER->firstname} {$USER->lastname} - {$USER->email}";
$watermark_line_2 = date('d/m/Y') . " - " . date('H:i');

// --- 1. VIDEO RENDERING ---
if ($capsula->showmode == 0 || $capsula->showmode == 1) {
    $video_files = $fs->get_area_files($context->id, 'mod_capsula', 'video', 0, 'sortorder, itemid, filepath, filename', false);
    if ($video_files) {
        $video = reset($video_files);
        $vurl = moodle_url::make_pluginfile_url($context->id, 'mod_capsula', 'video', 0, '/', $video->get_filename());
        ?>
        
        <div class="capsula-viewer-wrapper no-select" oncontextmenu="return false;">
            <!-- Watermark Centered (Single) -->
            <div class="capsula-watermark-overlay" style="z-index: 100; display: flex; align-items: center; justify-content: center;">
                <svg width="100%" height="100%" viewBox="0 0 1000 1000" preserveAspectRatio="xMidYMid meet">
                    <g transform="rotate(-28 500 500)">
                        <text x="500" y="500" text-anchor="middle" dominant-baseline="middle" class="wm-text">
                            <tspan x="500" dy="-20" font-size="40px"><?php echo $watermark_line_1; ?></tspan>
                            <tspan x="500" dy="55" font-size="28px"><?php echo $watermark_line_2; ?></tspan>
                        </text>
                    </g>
                </svg>
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
                
                <!-- Watermark Centered (Single) -->
                <div class="capsula-watermark-overlay" style="z-index: 200; display: flex; align-items: center; justify-content: center;">
                    <svg width="100%" height="100%" viewBox="0 0 1000 1000" preserveAspectRatio="xMidYMid meet">
                        <g transform="rotate(-28 500 500)">
                            <text x="500" y="500" text-anchor="middle" dominant-baseline="middle" class="wm-text">
                                <tspan x="500" dy="-20" font-size="40px"><?php echo $watermark_line_1; ?></tspan>
                                <tspan x="500" dy="55" font-size="28px"><?php echo $watermark_line_2; ?></tspan>
                            </text>
                        </g>
                    </svg>
                </div>

                <div id="pdf-scroll-container" oncontextmenu="return false;" class="no-select">
                     <div id="pdf-render-layer"></div>
                </div>
            </div>
        </div>

        <script>
            let pdfDoc = null;
            let currentScale = 'auto'; // Default to auto-fit
            const pdfUrl = '<?php echo $purl; ?>';

            // Security
            document.addEventListener('contextmenu', event => event.preventDefault());
            document.addEventListener('keydown', e => {
                if ((e.ctrlKey || e.metaKey) && ['s','p','u'].includes(e.key)) e.preventDefault();
            });

            pdfjsLib.getDocument(pdfUrl).promise.then(pdf => {
                pdfDoc = pdf;
                // Calculate Auto-Fit Scale based on first page
                pdf.getPage(1).then(page => {
                    const container = document.getElementById('capsula-pdf-wrapper');
                    const viewport = page.getViewport({ scale: 1.0 });
                    
                    // Fit width with small margin
                    const computedScale = (container.clientWidth / viewport.width) * 0.95;
                    currentScale = computedScale;
                    
                    // Render
                    renderAllPages(currentScale);
                });
            }).catch(err => {
                console.error("Error loading PDF: " + err);
                const container = document.getElementById('pdf-render-layer');
                if(container) container.innerHTML = "<div style='color:white; padding:20px;'>Error cargando documento.</div>";
            });

            function renderAllPages(scale) {
                const container = document.getElementById('pdf-render-layer');
                if(!container || !pdfDoc) return;
                
                container.innerHTML = '';
                const zoomLabel = document.getElementById('zoom-level');
                if(zoomLabel) zoomLabel.innerText = Math.round(scale * 100) + "%";

                const range = Array.from({length: pdfDoc.numPages}, (_, i) => i + 1);
                
                // Sequential rendering to maintain order
                range.reduce((promise, num) => {
                    return promise.then(() => pdfDoc.getPage(num).then(page => {
                        const viewport = page.getViewport({ scale: scale * 1.5 }); // High DPI
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');
                        
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;
                        
                        // Visual Style
                        // We use the exact computed width based on scale
                        const visualWidth = viewport.width / 1.5;
                        canvas.style.width = visualWidth + "px";
                        canvas.style.height = "auto";
                        canvas.style.maxWidth = "100%"; // Ensure it never overflows container width
                        
                        container.appendChild(canvas);

                        const renderContext = {
                            canvasContext: context,
                            viewport: viewport
                        };
                        return page.render(renderContext).promise;
                    }));
                }, Promise.resolve());
            }

            function zoomIn() {
                if (currentScale >= 3.0) return;
                currentScale += 0.20; // Smoother steps
                renderAllPages(currentScale);
            }

            function zoomOut() {
                if (currentScale <= 0.4) return;
                currentScale -= 0.20;
                renderAllPages(currentScale);
            }
        </script>
        <?php
    }
}

echo $OUTPUT->footer();
