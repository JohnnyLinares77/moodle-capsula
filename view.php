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
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    // Configuración global de PDF.js
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
</script>

<style>
    :root {
        --viewer-bg: #202124;
        --viewer-inner-bg: #2d2e30;
        --scrollbar-thumb: #5f6368;
        --scrollbar-track: #202124;
    }

    /* Contenedor Principal 16:9 */
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

    /* Header del PDF (Zoom controls) */
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
        display: flex; /* Asegura centrado de iconos si los hubiera */
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
        font-size: 18px; /* Tamaño de los iconos/texto */
    }
    .capsula-btn:hover {
        background-color: rgba(255,255,255,0.1);
    }
    
    /* Área de contenido (Video o PDF) */
    .capsula-content {
        flex: 1;
        position: relative;
        overflow: hidden;
        background-color: var(--viewer-inner-bg);
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* Video específico */
    .capsula-video {
        width: 100%;
        height: 100%;
        object-fit: contain; /* Mantiene aspecto sin recortar */
        outline: none;
    }

    /* PDF Scroll y Container */
    #pdf-scroll-container {
        width: 100%;
        height: 100%;
        overflow-y: auto;
        overflow-x: auto;
        position: relative;
        /* Scrollbar styling */
        scrollbar-width: thin;
        scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
    }
    
    #pdf-scroll-container::-webkit-scrollbar {
        width: 12px;
        height: 12px;
    }
    #pdf-scroll-container::-webkit-scrollbar-track {
        background: var(--scrollbar-track);
    }
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
        min-height: 100%; /* Permite que el overlay crezca con el scroll */
        position: relative;
        z-index: 1; /* Bajo el overlay */
    }

    canvas { 
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        margin-bottom: 15px;
        max-width: 95%; /* Responsive */
    }

    /* Overlay de Información (Estilo Drive) */
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

    /* Watermark de fondo (opcional, estilo anterior conservado pero sutil) */
    .watermark-bg {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%; /* Se ajustará dinámicamente en JS para PDF */
        pointer-events: none;
        z-index: 200; /* Por encima de todo para seguridad */
        overflow: hidden;
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        align-content: flex-start;
        opacity: 0.4; /* Muy sutil */
    }
    
    /* Prevenir selección y clic derecho */
    .no-select {
        user-select: none;
        -webkit-user-select: none;
    }
</style>

<?php
// Preparar datos del usuario
$user_info_primary = "{$USER->firstname} {$USER->lastname} - {$USER->idnumber} - {$USER->email}";
$user_info_secondary = date('d/m/Y') . " - " . date('H:i');

// --- RENDERING VIDEO ---
if ($capsula->showmode == 0 || $capsula->showmode == 1) {
    echo '<h3>'.get_string('videoview', 'mod_capsula').'</h3>'; // Opcional: título si se desea separar
    
    $video_files = $fs->get_area_files($context->id, 'mod_capsula', 'video', 0, 'sortorder, itemid, filepath, filename', false);
    if ($video_files) {
        $video = reset($video_files);
        $vurl = moodle_url::make_pluginfile_url($context->id, 'mod_capsula', 'video', 0, '/', $video->get_filename());
        ?>
        <div class="capsula-viewer-wrapper no-select" oncontextmenu="return false;">
            <!-- Info Overlay -->
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

// --- RENDERING PDF ---
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
                <!-- Info Overlay (Fixed on screen relative to viewer) -->
                <div class="capsula-info-overlay">
                    <span class="capsula-info-line capsula-info-primary"><?php echo $user_info_primary; ?></span>
                    <span class="capsula-info-line capsula-info-secondary"><?php echo $user_info_secondary; ?></span>
                </div>

                <!-- Scroll Container -->
                <div id="pdf-scroll-container" oncontextmenu="return false;" class="no-select">
                     <!-- Rendering Layer -->
                     <div id="pdf-render-layer"></div>
                </div>
            </div>
        </div>

        <script>
            let pdfDoc = null;
            let currentScale = 1.0; // Escala base
            let isRendering = false;
            const pdfUrl = '<?php echo $purl; ?>';

            // Evitar shortcuts y clic derecho
            document.addEventListener('contextmenu', event => event.preventDefault());
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'p' || e.key === 'u')) {
                    e.preventDefault();
                }
            });

            // Cargar documento
            pdfjsLib.getDocument(pdfUrl).promise.then(pdf => {
                pdfDoc = pdf;
                renderAllPages(currentScale);
            }).catch(err => {
                console.error("Error loading PDF: " + err);
                document.getElementById('pdf-render-layer').innerHTML = "<div style='color:white; padding:20px;'>Error cargando documento.</div>";
            });

            function renderAllPages(scale) {
                const container = document.getElementById('pdf-render-layer');
                container.innerHTML = ''; // Limpiar previo render
                
                // Actualizar label de zoom
                document.getElementById('zoom-level').innerText = Math.round(scale * 100) + "%";

                const renderPage = (num) => {
                    if (num > pdfDoc.numPages) return;

                    pdfDoc.getPage(num).then(page => {
                        const viewport = page.getViewport({ scale: scale * 1.5 }); // High-DPI upscale logic
                        // Ajustamos dimensiones canvas based on scale
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');
                        
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;
                        
                        // Controlar tamaño visual vs resolución real
                        // La clase CSS max-width: 95% se encarga de que no desborde horizontalmente
                        // pero queremos que el usuario pueda hacer zoom real.
                        // Para zoom real, quizás sea mejor dejar que el canvas crezca y el container tenga scroll.
                        // Modificamos el estilo inline del canvas para que obedezca al scale si supera el contenedor
                        
                        canvas.style.width = (viewport.width / 1.5) + "px"; 
                        canvas.style.height = (viewport.height / 1.5) + "px";
                        canvas.style.maxWidth = 'none'; // Overrule CSS restriction for zoom functionality

                        container.appendChild(canvas);

                        const renderContext = {
                            canvasContext: context,
                            viewport: viewport
                        };
                        
                        page.render(renderContext).promise.then(() => {
                            renderPage(num + 1); // Render next page sequentially
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

