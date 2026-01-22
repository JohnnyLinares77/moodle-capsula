<?php
require_once('../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT); // ID del Course Module
$cm = get_coursemodule_from_id('visorpdf', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$visorpdf = $DB->get_record('visorpdf', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/visorpdf:view', $context);

// 1. Obtener el archivo de la tabla m_files
$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'mod_visorpdf', 'content', 0, 'itemid, filepath, filename', false);

if (empty($files)) {
    print_error('filenotfound', 'error');
}

$file = reset($files);
// Generar la URL protegida
$fileurl = moodle_url::make_pluginfile_url($context->id, 'mod_visorpdf', 'content', 0, $file->get_filepath(), $file->get_filename());

$PAGE->set_url('/mod/visorpdf/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($visorpdf->name));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

// Datos para la marca de agua
$user_info = $USER->firstname . ' ' . $USER->lastname . ' - ' . $USER->email;
$date_info = userdate(time(), '%d/%m/%Y %H:%M');
$watermark_text = $user_info . ' - ' . $date_info;

?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<style>
    /* Contenedor Principal */
    #pdf-wrapper {
        position: relative;
        background: #525659;
        width: 100%;
        height: 85vh; /* Alto adaptable */
        display: flex;
        flex-direction: column;
        border: 1px solid #ccc;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }

    /* Barra de Herramientas (Zoom) */
    #toolbar {
        background: #333;
        color: #fff;
        padding: 10px;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 15px;
        z-index: 20;
        box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    }
    #toolbar button {
        background: #444;
        border: 1px solid #666;
        color: white;
        padding: 5px 12px;
        cursor: pointer;
        border-radius: 4px;
        font-weight: bold;
    }
    #toolbar button:hover { background: #666; }
    #page-count { font-family: monospace; }

    /* Área de Scroll */
    #pdf-container {
        flex: 1;
        overflow: auto;
        position: relative;
        text-align: center;
        padding: 20px;
        user-select: none; /* Evita seleccionar texto */
    }

    /* Lienzos (Páginas) */
    canvas {
        display: block;
        margin: 0 auto 20px auto;
        box-shadow: 0 0 15px rgba(0,0,0,0.5);
        max-width: 100%; /* Responsive: no desbordar ancho contenedor */
        height: auto;
    }

    /* Marca de Agua (Overlay CSS puro) */
    .watermark-container {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%; /* Crece con el scroll del contenedor */
        pointer-events: none; /* Permite scroll y clicks a través */
        z-index: 10;
        overflow: hidden;
        display: flex;
        flex-wrap: wrap;
        align-content: flex-start;
        justify-content: space-around;
        gap: 100px; /* Espaciado entre repeticiones */
        padding-top: 50px;
    }

    .watermark-text {
        color: rgba(255, 0, 0, 0.15); /* Rojo semitransparente */
        font-size: 22px;
        font-family: sans-serif;
        transform: rotate(-30deg);
        white-space: nowrap;
        font-weight: bold;
        user-select: none;
    }

    /* Bloqueo de Impresión */
    @media print { body { display: none !important; } }
</style>

<div id="pdf-wrapper">
    <!-- Barra de Herramientas -->
    <div id="toolbar">
        <button onclick="zoomOut()">-</button>
        <button onclick="zoomReset()">100%</button>
        <button onclick="zoomIn()">+</button>
    </div>

    <!-- Contenedor con Scroll -->
    <div id="pdf-container" oncontextmenu="return false;">
        <!-- Capa de Marca de Agua -->
        <div class="watermark-container" id="watermark-layer">
            <!-- Se rellenará con JS o PHP, usaremos PHP para server-side render inicial -->
            <?php 
                // Generamos muchas repeticiones para cubrir un documento largo
                for($i=0; $i<100; $i++) {
                    echo '<div class="watermark-text">' . $watermark_text . '</div>';
                }
            ?>
        </div>
        
        <!-- Aquí se dibujan los canvas -->
        <div id="pdf-render"></div>
    </div>
</div>

<script>
    const url = '<?php echo $fileurl; ?>';
    const pdfjsLib = window['pdfjs-dist/build/pdf'];
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    let pdfDoc = null;
    let currentScale = 1.2; // Zoom inicial
    let isRendering = false;

    // Cargar Documento
    pdfjsLib.getDocument(url).promise.then(pdf => {
        pdfDoc = pdf;
        renderAllPages();
    }).catch(err => {
        console.error('Error cargando PDF:', err);
        document.getElementById('pdf-render').innerHTML = '<p style="color:white;">Error cargando el documento.</p>';
    });

    // Función para renderizar todas las páginas
    function renderAllPages() {
        const container = document.getElementById('pdf-render');
        container.innerHTML = ''; // Limpiar canvas previos

        for (let i = 1; i <= pdfDoc.numPages; i++) {
            renderPage(i);
        }
        
        // Ajustar altura de la capa de marca de agua
        setTimeout(() => {
            const contentHeight = container.scrollHeight;
            document.getElementById('watermark-layer').style.height = (contentHeight + 500) + 'px'; 
        }, 1000);
    }

    // Renderizar una página individual
    function renderPage(num) {
        pdfDoc.getPage(num).then(page => {
            const viewport = page.getViewport({ scale: currentScale });
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            document.getElementById('pdf-render').appendChild(canvas);

            page.render({ canvasContext: ctx, viewport: viewport });
        });
    }

    // Controles de Zoom
    function zoomIn() {
        currentScale += 0.2;
        renderAllPages();
    }

    function zoomOut() {
        if (currentScale > 0.6) {
            currentScale -= 0.2;
            renderAllPages();
        }
    }

    function zoomReset() {
        currentScale = 1.2;
        renderAllPages();
    }

    // Bloqueos de teclado
    document.addEventListener('keydown', e => {
        if (e.ctrlKey && (e.key === 'p' || e.key === 's' || e.key === 'u' || e.key === 'Shift')) {
            e.preventDefault();
        }
    });

    // Auto-ajuste de altura de marca de agua al hacer scroll infinito
    const scrollContainer = document.getElementById('pdf-container');
    scrollContainer.addEventListener('scroll', () => {
         // Lógica opcional si se necesitara recargar marcas de agua
    });
</script>

<?php
echo $OUTPUT->footer();
