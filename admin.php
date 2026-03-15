<?php
session_start();

// Default credentials
$valid_username = 'admin';
$valid_password = 'pass';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === $valid_username && $_POST['password'] === $valid_password) {
        $_SESSION['logged_in'] = true;
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    } else {
        $login_error = 'Invalid credentials';
    }
}

// Handle save configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config']) && isset($_SESSION['logged_in'])) {
    $config = [
        'sections' => json_decode($_POST['sections'], true),
        'platforms' => json_decode($_POST['platforms'], true),
        'features' => json_decode($_POST['features'], true),
        'extras' => json_decode($_POST['extras'], true),
        'timeframes' => json_decode($_POST['timeframes'], true)
    ];
    
    // Generate the new HTML file
    $html_content = generateHtmlFromConfig($config);
    file_put_contents('generated_brief.html', $html_content);
    $save_success = 'Configuration saved and HTML generated successfully!';
}

function generateHtmlFromConfig($config) {
    $platforms_html = '';
    foreach ($config['platforms'] as $platform) {
        $platforms_html .= sprintf(
            '<label><input type="checkbox" value="%s" data-cost="%s" class="platform-check"> %s</label>',
            htmlspecialchars($platform['value']),
            htmlspecialchars($platform['cost']),
            htmlspecialchars($platform['label'])
        );
    }
    
    $features_html = '';
    foreach ($config['features'] as $feature) {
        $features_html .= sprintf(
            '<label><input type="checkbox" value="%s" data-cost="%s"> %s</label>',
            htmlspecialchars($feature['value']),
            htmlspecialchars($feature['cost']),
            htmlspecialchars($feature['label'])
        );
    }
    
    $extras_html = '';
    foreach ($config['extras'] as $extra) {
        $extras_html .= sprintf(
            '<label><input type="checkbox" value="%s" data-cost="%s"> %s</label>',
            htmlspecialchars($extra['value']),
            htmlspecialchars($extra['cost']),
            htmlspecialchars($extra['label'])
        );
    }
    
    // Timeframes logic
    $timeframes_js = json_encode($config['timeframes']);
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project brief · Multi-page A4 PDF with colors</title>
    <!-- Tailwind + Font Awesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- html2canvas + jsPDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        /* ensure all inputs have placeholders, and pdf will render them as static text via cloning */
        input, textarea {
            line-height: 1.5 !important;
            padding: 0.75rem !important;
            white-space: normal !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            height: auto !important;
            min-height: 3rem;
            box-sizing: border-box !important;
            background-color: white;
        }
        textarea {
            min-height: 6rem;
            resize: vertical;
        }
        /* grid for checkboxes with consistent alignment */
        .checkbox-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1.5rem;
            align-items: center;
        }
        .checkbox-grid label {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            white-space: nowrap;
        }
        /* image grid - preserve original aspect */
        .image-grid-img {
            width: 100%;
            height: auto;
            object-fit: contain;
            background-color: #f8fafc;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            display: block;
        }
        .image-item {
            transition: all 0.15s;
            position: relative;
            display: flex;
            justify-content: center;
            background: #f1f5f9;
            border-radius: 0.5rem;
        }
        .remove-image-btn {
            opacity: 0;
            transition: opacity 0.15s;
            position: absolute;
            top: -0.5rem;
            right: -0.5rem;
            background-color: #ef4444;
            color: white;
            border-radius: 9999px;
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            border: 2px solid white;
            cursor: pointer;
            z-index: 10;
        }
        .image-item:hover .remove-image-btn {
            opacity: 1;
        }
        .spinner {
            border: 2px solid #f1f5f9;
            border-top: 2px solid #3b82f6;
            border-radius: 50%;
            width: 1.2rem;
            height: 1.2rem;
            animation: spin 0.7s linear infinite;
            display: inline-block;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        /* color picker row */
        .palette-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            background: #f8fafc;
            padding: 1rem;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
        }
        .color-palette-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            background: white;
            padding: 0.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            min-width: 100px;
            border: 1px solid #e9eef2;
        }
        .color-swatch-large {
            width: 3rem;
            height: 3rem;
            border-radius: 0.5rem;
            border: 2px solid #e2e8f0;
            margin-bottom: 0.25rem;
            cursor: pointer;
        }
        .color-hex-display {
            font-size: 0.7rem;
            background: #f1f5f9;
            padding: 0.2rem;
            border-radius: 0.25rem;
            margin-top: 0.2rem;
            width: 100%;
            text-align: center;
        }
        .remove-color-btn {
            margin-top: 0.25rem;
            font-size: 0.7rem;
            color: #ef4444;
            background: none;
            border: none;
            cursor: pointer;
        }
    </style>
</head>
<body class="bg-slate-50 font-sans antialiased p-4 md:p-6">

    <div class="max-w-6xl mx-auto">
        <!-- capture area (everything except buttons) -->
        <div id="pdfCaptureArea" class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="h-1 bg-transparent"></div>

            <div class="p-6 md:p-8 space-y-8">
                <!-- header -->
                <h1 class="text-3xl md:text-4xl font-bold text-slate-800 flex items-center gap-3">
                    <i class="fa-solid fa-pen-ruler text-indigo-500"></i>
                    <span>Project brief</span>
                </h1>

                <!-- ===== CLIENT ===== -->
                <section>
                    <h2 class="text-lg font-semibold text-slate-700 mb-3 flex items-center gap-2 border-b border-slate-100 pb-2"><i class="fa-regular fa-address-card text-indigo-400"></i> client info</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <input id="fullName" type="text" placeholder="e.g. Alex Rivera" class="w-full border border-slate-200 rounded-lg bg-white">
                        <input id="email" type="email" placeholder="alex@example.com" class="w-full border border-slate-200 rounded-lg bg-white">
                        <input id="company" type="text" placeholder="Creative Studio (optional)" class="w-full border border-slate-200 rounded-lg bg-white">
                        <input id="phone" type="tel" placeholder="+1 555 123 4567" class="w-full border border-slate-200 rounded-lg bg-white">
                    </div>
                </section>

                <!-- ===== PROJECT ===== -->
                <section>
                    <h2 class="text-lg font-semibold text-slate-700 mb-3 flex items-center gap-2 border-b border-slate-100 pb-2"><i class="fa-regular fa-file-lines text-indigo-400"></i> project details</h2>
                    <input id="projectName" type="text" placeholder="Project name (e.g. TaskFlow Pro)" class="w-full border border-slate-200 rounded-lg mb-4 bg-white">
                    <textarea id="description" rows="3" placeholder="Describe the core idea, goals, target audience, and any must-have features..." class="w-full border border-slate-200 rounded-lg bg-white"></textarea>
                </section>

                <!-- ===== PLATFORMS ===== -->
                <section>
                    <h2 class="text-lg font-semibold text-slate-700 mb-3 flex items-center gap-2 border-b border-slate-100 pb-2"><i class="fa-regular fa-window-maximize text-indigo-400"></i> target platforms</h2>
                    <div class="checkbox-grid">
                        {$platforms_html}
                    </div>
                </section>

                <!-- ===== FEATURES ===== -->
                <section>
                    <h2 class="text-lg font-semibold text-slate-700 mb-3 flex items-center gap-2 border-b border-slate-100 pb-2"><i class="fa-regular fa-rectangle-list text-indigo-400"></i> features & modules</h2>
                    <div class="checkbox-grid">
                        {$features_html}
                    </div>
                    <div class="mt-4">
                        <input id="customFeatures" type="text" placeholder="Additional custom features (comma separated)" class="w-full border border-slate-200 rounded-lg bg-white">
                    </div>
                </section>

                <!-- ===== EXTRAS ===== -->
                <section>
                    <h2 class="text-lg font-semibold text-slate-700 mb-3 flex items-center gap-2 border-b border-slate-100 pb-2"><i class="fa-regular fa-gem text-indigo-400"></i> extras / add-ons</h2>
                    <div class="checkbox-grid">
                        {$extras_html}
                    </div>
                </section>

                <!-- ===== THEME COLORS ===== -->
                <section>
                    <h2 class="text-lg font-semibold text-slate-700 mb-3 flex items-center gap-2 border-b border-slate-100 pb-2"><i class="fa-solid fa-palette text-indigo-400"></i> theme colors (HEX picker)</h2>
                    <div id="paletteRow" class="palette-row">
                        <!-- dynamic color items injected here -->
                    </div>
                    <button id="addColorBtn" type="button" class="mt-3 text-sm bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-4 py-2 rounded-lg border border-indigo-200 inline-flex items-center gap-2"><i class="fa-regular fa-plus"></i> add color (max 5)</button>
                </section>

                <!-- ===== IMAGE MANAGER ===== -->
                <section>
                    <h2 class="text-lg font-semibold text-slate-700 mb-3 flex items-center gap-2 border-b border-slate-100 pb-2"><i class="fa-regular fa-images text-indigo-400"></i> reference images (original aspect)</h2>
                    <div class="flex items-center gap-3">
                        <label for="imageUpload" class="cursor-pointer bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-medium px-5 py-3 rounded-lg border border-indigo-200 inline-flex items-center gap-2 transition">
                            <i class="fa-regular fa-plus"></i> add images
                        </label>
                        <input type="file" id="imageUpload" multiple accept="image/*" class="hidden">
                        <span class="text-sm text-slate-400" id="imageCounter">0 images</span>
                    </div>
                    <div id="imageGrid" class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-5"></div>
                </section>

                <!-- ===== NOTES ===== -->
                <section>
                    <h2 class="text-lg font-semibold text-slate-700 mb-3 flex items-center gap-2 border-b border-slate-100 pb-2"><i class="fa-regular fa-note-sticky text-indigo-400"></i> notes / timeline / budget</h2>
                    <textarea id="notes" rows="3" placeholder="e.g. Q4 2025, budget $25k-$35k, integrations with Slack & Google Calendar, pastel colors..." class="w-full border border-slate-200 rounded-lg bg-white"></textarea>
                </section>

                <!-- ===== ESTIMATE ===== -->
                <section class="bg-slate-50 p-6 rounded-xl border border-slate-200">
                    <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2"><i class="fa-solid fa-chart-line text-green-600"></i> estimate snapshot</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div><span class="text-sm text-slate-500">estimated price</span><div id="priceDisplay" class="text-3xl font-bold text-indigo-700">$0</div></div>
                        <div><span class="text-sm text-slate-500">complexity</span><div id="complexityDisplay" class="text-2xl font-semibold">Low</div></div>
                        <div><span class="text-sm text-slate-500">timeframe</span><div id="timeDisplay" class="text-2xl font-semibold">1-2 weeks</div></div>
                    </div>
                </section>

                <div class="text-xs text-slate-300 text-right">all fields editable · HEX colors only</div>
            </div>
        </div> <!-- end capture area -->

        <!-- action button -->
        <div class="mt-8 flex flex-wrap justify-end gap-4">
            <button id="exportPdfBtn" class="px-8 py-3 bg-indigo-700 hover:bg-indigo-800 text-white rounded-xl shadow-md flex items-center gap-3 text-lg font-medium transition"><i class="fa-regular fa-file-pdf"></i> Export PDF (A4 multi-page)</button>
        </div>
        <p class="text-xs text-right text-slate-400 mt-3">PDF fits A4 width, flows to multiple pages, includes theme colors.</p>
    </div>

    <script>
        (function() {
            // DOM elements
            const fullName = document.getElementById('fullName');
            const email = document.getElementById('email');
            const company = document.getElementById('company');
            const phone = document.getElementById('phone');
            const projectName = document.getElementById('projectName');
            const description = document.getElementById('description');
            const customFeatures = document.getElementById('customFeatures');
            const notes = document.getElementById('notes');
            const imageUpload = document.getElementById('imageUpload');
            const imageGrid = document.getElementById('imageGrid');
            const imageCounter = document.getElementById('imageCounter');
            const exportBtn = document.getElementById('exportPdfBtn');
            const captureArea = document.getElementById('pdfCaptureArea');
            const addColorBtn = document.getElementById('addColorBtn');
            const paletteRow = document.getElementById('paletteRow');

            const priceDisplay = document.getElementById('priceDisplay');
            const complexityDisplay = document.getElementById('complexityDisplay');
            const timeDisplay = document.getElementById('timeDisplay');
            const allCheckboxes = document.querySelectorAll('input[type=checkbox][data-cost]');

            // Timeframes configuration
            const timeframes = {$timeframes_js};

            // ----- Color palette with HEX only -----
            let colorItems = [
                { hex: '#303633' },
                { hex: '#8be8cb' },
                { hex: '#7ea2aa' }
            ];
            const MAX_COLORS = 5;

            function renderPalette() {
                let html = '';
                colorItems.forEach((item, index) => {
                    html += `
                        <div class="color-palette-item" data-index="\${index}">
                            <div class="color-swatch-large" style="background-color: \${item.hex};"></div>
                            <div class="color-hex-display">\${item.hex}</div>
                            \${index > 0 ? `<button class="remove-color-btn" data-index="\${index}"><i class="fa-regular fa-trash-can"></i> remove</button>` : ''}
                        </div>
                    `;
                });
                paletteRow.innerHTML = html;

                document.querySelectorAll('.color-swatch-large').forEach((swatch, idx) => {
                    swatch.addEventListener('click', function(e) {
                        const input = document.createElement('input');
                        input.type = 'color';
                        input.value = colorItems[idx].hex;
                        input.addEventListener('input', (e) => {
                            colorItems[idx].hex = e.target.value;
                            renderPalette();
                        });
                        input.click();
                    });
                });

                document.querySelectorAll('.remove-color-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const idx = btn.dataset.index;
                        colorItems.splice(idx, 1);
                        renderPalette();
                        addColorBtn.disabled = colorItems.length >= MAX_COLORS;
                        addColorBtn.classList.toggle('opacity-50', colorItems.length >= MAX_COLORS);
                    });
                });
            }

            renderPalette();
            addColorBtn.addEventListener('click', () => {
                if (colorItems.length < MAX_COLORS) {
                    colorItems.push({ hex: '#aabbcc' });
                    renderPalette();
                }
                addColorBtn.disabled = colorItems.length >= MAX_COLORS;
                addColorBtn.classList.toggle('opacity-50', colorItems.length >= MAX_COLORS);
            });

            // ----- image manager -----
            let imageList = [];
            function renderImageGrid() {
                if (imageList.length === 0) {
                    imageGrid.innerHTML = `<div class="col-span-full text-slate-400 text-sm italic py-6 text-center border border-dashed rounded-lg">no images added</div>`;
                } else {
                    let html = '';
                    imageList.forEach((img) => {
                        html += `
                            <div class="relative image-item">
                                <img src="\${img.dataURL}" class="image-grid-img" alt="ref" loading="lazy">
                                <button class="remove-image-btn" data-id="\${img.id}"><i class="fa-regular fa-times"></i></button>
                            </div>
                        `;
                    });
                    imageGrid.innerHTML = html;
                    document.querySelectorAll('.remove-image-btn').forEach(btn => {
                        btn.addEventListener('click', (e) => {
                            const id = btn.dataset.id;
                            imageList = imageList.filter(img => img.id != id);
                            renderImageGrid();
                            updateImageCounter();
                        });
                    });
                }
                updateImageCounter();
            }
            function updateImageCounter() {
                imageCounter.innerText = imageList.length + ' image' + (imageList.length !== 1 ? 's' : '');
            }
            imageUpload.addEventListener('change', function(e) {
                const files = Array.from(e.target.files);
                files.forEach(file => {
                    if (!file.type.startsWith('image/')) return;
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        imageList.push({
                            dataURL: ev.target.result,
                            id: Date.now() + Math.random() + '-' + file.name
                        });
                        renderImageGrid();
                    };
                    reader.readAsDataURL(file);
                });
                imageUpload.value = '';
            });
            renderImageGrid();

            // ----- estimate calculation with configurable timeframes -----
            function calculateEstimate() {
                let total = 0;
                let count = 0;
                allCheckboxes.forEach(cb => {
                    if (cb.checked) {
                        total += Number(cb.dataset.cost) || 0;
                        count++;
                    }
                });
                priceDisplay.innerText = '$' + total;
                
                let complexity = 'Low';
                let time = timeframes.low;
                
                if (count > 4 && count <= 8) {
                    complexity = 'Medium';
                    time = timeframes.medium;
                } else if (count > 8) {
                    complexity = 'High';
                    time = timeframes.high;
                }
                complexityDisplay.innerText = complexity;
                timeDisplay.innerText = time;
            }
            allCheckboxes.forEach(cb => cb.addEventListener('change', calculateEstimate));
            calculateEstimate();

            // ----- PDF export: multi-page A4 with proper width, INCLUDING theme colors -----
            exportBtn.addEventListener('click', async function() {
                const originalContent = exportBtn.innerHTML;
                exportBtn.innerHTML = `<span class="spinner mr-2"></span> generating PDF...`;
                exportBtn.disabled = true;

                try {
                    await new Promise(resolve => setTimeout(resolve, 100));

                    // IMPORTANT: Do NOT hide the palette row - we want colors in the PDF
                    // We only hide the add button to keep it clean
                    const addBtn = addColorBtn;
                    const addBtnDisplay = addBtn.style.display;
                    addBtn.style.display = 'none';

                    // Create canvas from the entire capture area
                    const canvas = await html2canvas(captureArea, {
                        scale: 2,
                        backgroundColor: '#ffffff',
                        logging: false,
                        useCORS: true,
                        allowTaint: false,
                        windowWidth: captureArea.scrollWidth,
                        windowHeight: captureArea.scrollHeight,
                        onclone: (clonedDoc) => {
                            // Replace inputs with static text
                            const inputs = clonedDoc.querySelectorAll('input:not([type=checkbox]):not([type=color]), textarea');
                            inputs.forEach(el => {
                                const parent = el.parentNode;
                                const value = el.value || el.placeholder || '';
                                const staticDiv = clonedDoc.createElement('div');
                                staticDiv.className = 'border border-slate-200 rounded-lg bg-white p-3 min-h-[3rem]';
                                staticDiv.style.lineHeight = '1.5';
                                staticDiv.style.whiteSpace = 'pre-wrap';
                                staticDiv.style.wordWrap = 'break-word';
                                staticDiv.style.padding = '0.75rem';
                                staticDiv.textContent = value || ' ';
                                parent.replaceChild(staticDiv, el);
                            });
                            
                            // Also hide the add button in the clone
                            const cloneAddBtn = clonedDoc.getElementById('addColorBtn');
                            if (cloneAddBtn) {
                                cloneAddBtn.style.display = 'none';
                            }
                        }
                    });

                    // Restore add button visibility
                    addBtn.style.display = addBtnDisplay;

                    // Create PDF with A4 dimensions
                    const { jsPDF } = window.jspdf;
                    const pdf = new jsPDF({
                        orientation: 'portrait',
                        unit: 'mm',
                        format: 'a4'
                    });

                    const pdfWidth = pdf.internal.pageSize.getWidth();
                    const pdfHeight = pdf.internal.pageSize.getHeight();
                    
                    // Calculate the scaling factor to fit the canvas width to A4 width
                    const scale = pdfWidth / canvas.width;
                    
                    // Calculate the height of the content in PDF units
                    const contentHeight = canvas.height * scale;
                    
                    // Calculate how many pages we need
                    const pageHeight = pdfHeight;
                    let remainingHeight = contentHeight;
                    let currentPosition = 0;
                    
                    // Add first page
                    let pageIndex = 0;
                    
                    while (remainingHeight > 0) {
                        if (pageIndex > 0) {
                            pdf.addPage();
                        }
                        
                        // Calculate the portion of the canvas to render on this page
                        const sourceY = currentPosition / scale;
                        const pageCanvasHeight = Math.min(pageHeight / scale, canvas.height - sourceY);
                        
                        // Create a canvas for this page
                        const pageCanvas = document.createElement('canvas');
                        pageCanvas.width = canvas.width;
                        pageCanvas.height = pageCanvasHeight;
                        const ctx = pageCanvas.getContext('2d');
                        ctx.drawImage(canvas, 0, sourceY, canvas.width, pageCanvasHeight, 0, 0, canvas.width, pageCanvasHeight);
                        
                        // Add image to PDF
                        const pageImgData = pageCanvas.toDataURL('image/jpeg', 0.95);
                        pdf.addImage(pageImgData, 'JPEG', 0, 0, pdfWidth, pageCanvasHeight * scale, undefined, 'FAST');
                        
                        remainingHeight -= pageHeight;
                        currentPosition += pageHeight;
                        pageIndex++;
                    }

                    pdf.save(`brief_\${projectName.value.trim() || 'project'}.pdf`);

                } catch (error) {
                    console.error('PDF error', error);
                    alert('PDF generation failed. Please try again.');
                } finally {
                    exportBtn.innerHTML = originalContent;
                    exportBtn.disabled = false;
                }
            });
        })();
    </script>
    <noscript>JavaScript required.</noscript>
</body>
</html>
HTML;
}

// Default configuration
$default_config = [
    'sections' => ['client', 'project', 'platforms', 'features', 'extras', 'colors', 'images', 'notes', 'estimate'],
    'platforms' => [
        ['label' => 'Web App', 'value' => 'Web App', 'cost' => 1000],
        ['label' => 'Mobile App', 'value' => 'Mobile App', 'cost' => 2000],
        ['label' => 'Desktop', 'value' => 'Desktop App', 'cost' => 1500],
        ['label' => 'SaaS', 'value' => 'SaaS', 'cost' => 3000]
    ],
    'features' => [
        ['label' => 'Authentication', 'value' => 'Authentication', 'cost' => 800],
        ['label' => 'Dashboard', 'value' => 'Dashboard', 'cost' => 900],
        ['label' => 'User Profiles', 'value' => 'User Profiles', 'cost' => 700],
        ['label' => 'Payments', 'value' => 'Payments', 'cost' => 1200],
        ['label' => 'Notifications', 'value' => 'Notifications', 'cost' => 500],
        ['label' => 'Admin Panel', 'value' => 'Admin Panel', 'cost' => 1500],
        ['label' => 'API', 'value' => 'API', 'cost' => 1000],
        ['label' => 'File Uploads', 'value' => 'File Uploads', 'cost' => 600],
        ['label' => 'Search', 'value' => 'Search', 'cost' => 500],
        ['label' => 'Real-time', 'value' => 'Real-time', 'cost' => 1300],
        ['label' => 'Analytics', 'value' => 'Analytics', 'cost' => 1100],
        ['label' => 'Multi-language', 'value' => 'Multi-language', 'cost' => 950]
    ],
    'extras' => [
        ['label' => 'Logo creation', 'value' => 'Logo creation', 'cost' => 500],
        ['label' => 'Ads (common sizes)', 'value' => 'Ads (common sizes)', 'cost' => 700],
        ['label' => 'Landing page', 'value' => 'Landing page', 'cost' => 1200],
        ['label' => 'SEO post', 'value' => 'SEO post', 'cost' => 300],
        ['label' => 'Video / explainer', 'value' => 'Video / explainer', 'cost' => 1500],
        ['label' => 'Extra revisions', 'value' => 'Extra revisions', 'cost' => 400],
        ['label' => 'Social media kit', 'value' => 'Social media kit', 'cost' => 600],
        ['label' => 'Content writing', 'value' => 'Content writing', 'cost' => 800]
    ],
    'timeframes' => [
        'low' => '1-2 weeks',
        'medium' => '3-6 weeks',
        'high' => '2-3 months'
    ]
];

// Load saved config if exists
$config = $default_config;
if (file_exists('brief_config.json')) {
    $saved_config = json_decode(file_get_contents('brief_config.json'), true);
    if ($saved_config) {
        $config = array_merge($default_config, $saved_config);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin · Project Brief Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .config-item {
            background: #f8fafc;
            border-radius: 0.5rem;
            padding: 1rem;
            border: 1px solid #e2e8f0;
        }
        .config-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .item-row {
            display: grid;
            grid-template-columns: 1fr 100px 80px;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            align-items: center;
        }
        .item-row input {
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
        }
        .remove-btn {
            color: #ef4444;
            cursor: pointer;
            background: none;
            border: none;
            font-size: 1.25rem;
        }
        .add-btn {
            background: #3b82f6;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
        }
        .add-btn:hover {
            background: #2563eb;
        }
        .nav-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 0.5rem;
        }
        .nav-tab {
            cursor: pointer;
            padding: 0.5rem 1rem;
            color: #64748b;
            font-weight: 500;
        }
        .nav-tab.active {
            color: #3b82f6;
            border-bottom: 2px solid #3b82f6;
            margin-bottom: -0.5rem;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body class="bg-slate-50 font-sans antialiased p-4 md:p-6">
    <div class="max-w-6xl mx-auto">
        <?php if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true): ?>
            <!-- Login Form -->
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8 max-w-md mx-auto">
                <h1 class="text-3xl font-bold text-slate-800 mb-6 flex items-center gap-3">
                    <i class="fa-solid fa-lock text-indigo-500"></i>
                    <span>Admin Login</span>
                </h1>
                
                <?php if (isset($login_error)): ?>
                    <div class="bg-red-50 text-red-700 p-3 rounded-lg mb-4"><?php echo $login_error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                        <input type="text" name="username" value="admin" class="w-full border border-slate-200 rounded-lg p-3">
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                        <input type="password" name="password" value="pass" class="w-full border border-slate-200 rounded-lg p-3">
                    </div>
                    <button type="submit" name="login" class="w-full bg-indigo-700 hover:bg-indigo-800 text-white font-medium py-3 px-4 rounded-lg transition">
                        <i class="fa-solid fa-arrow-right-to-bracket mr-2"></i> Login
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- Admin Panel -->
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
                <div class="p-6 md:p-8">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-3xl font-bold text-slate-800 flex items-center gap-3">
                            <i class="fa-solid fa-gear text-indigo-500"></i>
                            <span>Admin Configuration</span>
                        </h1>
                        <div class="flex gap-3">
                            <a href="?logout=1" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition flex items-center gap-2">
                                <i class="fa-solid fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>

                    <?php if (isset($save_success)): ?>
                        <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6 flex items-center gap-3">
                            <i class="fa-regular fa-circle-check text-xl"></i>
                            <?php echo $save_success; ?>
                            <a href="generated_brief.html" target="_blank" class="ml-auto bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                                <i class="fa-regular fa-eye mr-2"></i> View Generated Form
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Navigation Tabs -->
                    <div class="nav-tabs">
                        <div class="nav-tab active" data-tab="platforms">Platforms</div>
                        <div class="nav-tab" data-tab="features">Features & Modules</div>
                        <div class="nav-tab" data-tab="extras">Extras / Add-ons</div>
                        <div class="nav-tab" data-tab="timeframes">Timeframes</div>
                    </div>

                    <form method="POST" id="configForm">
                        <!-- Platforms Tab -->
                        <div id="platforms" class="tab-content active">
                            <h2 class="text-xl font-semibold text-slate-700 mb-4">Configure Platforms</h2>
                            <div id="platforms-container" class="space-y-4">
                                <?php foreach ($config['platforms'] as $index => $platform): ?>
                                <div class="config-item">
                                    <div class="config-item-header">
                                        <span class="font-medium">Platform #<?php echo $index + 1; ?></span>
                                        <button type="button" class="remove-btn" onclick="removeItem(this, 'platforms')"><i class="fa-regular fa-trash-can"></i></button>
                                    </div>
                                    <div class="item-row">
                                        <input type="text" name="platforms[<?php echo $index; ?>][label]" value="<?php echo htmlspecialchars($platform['label']); ?>" placeholder="Label (e.g. Web App)">
                                        <input type="number" name="platforms[<?php echo $index; ?>][cost]" value="<?php echo $platform['cost']; ?>" placeholder="Cost">
                                        <input type="text" name="platforms[<?php echo $index; ?>][value]" value="<?php echo htmlspecialchars($platform['value']); ?>" placeholder="Value">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="add-btn mt-4" onclick="addPlatform()"><i class="fa-regular fa-plus mr-2"></i> Add Platform</button>
                        </div>

                        <!-- Features Tab -->
                        <div id="features" class="tab-content">
                            <h2 class="text-xl font-semibold text-slate-700 mb-4">Configure Features & Modules</h2>
                            <div id="features-container" class="space-y-4">
                                <?php foreach ($config['features'] as $index => $feature): ?>
                                <div class="config-item">
                                    <div class="config-item-header">
                                        <span class="font-medium">Feature #<?php echo $index + 1; ?></span>
                                        <button type="button" class="remove-btn" onclick="removeItem(this, 'features')"><i class="fa-regular fa-trash-can"></i></button>
                                    </div>
                                    <div class="item-row">
                                        <input type="text" name="features[<?php echo $index; ?>][label]" value="<?php echo htmlspecialchars($feature['label']); ?>" placeholder="Label">
                                        <input type="number" name="features[<?php echo $index; ?>][cost]" value="<?php echo $feature['cost']; ?>" placeholder="Cost">
                                        <input type="text" name="features[<?php echo $index; ?>][value]" value="<?php echo htmlspecialchars($feature['value']); ?>" placeholder="Value">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="add-btn mt-4" onclick="addFeature()"><i class="fa-regular fa-plus mr-2"></i> Add Feature</button>
                        </div>

                        <!-- Extras Tab -->
                        <div id="extras" class="tab-content">
                            <h2 class="text-xl font-semibold text-slate-700 mb-4">Configure Extras / Add-ons</h2>
                            <div id="extras-container" class="space-y-4">
                                <?php foreach ($config['extras'] as $index => $extra): ?>
                                <div class="config-item">
                                    <div class="config-item-header">
                                        <span class="font-medium">Extra #<?php echo $index + 1; ?></span>
                                        <button type="button" class="remove-btn" onclick="removeItem(this, 'extras')"><i class="fa-regular fa-trash-can"></i></button>
                                    </div>
                                    <div class="item-row">
                                        <input type="text" name="extras[<?php echo $index; ?>][label]" value="<?php echo htmlspecialchars($extra['label']); ?>" placeholder="Label">
                                        <input type="number" name="extras[<?php echo $index; ?>][cost]" value="<?php echo $extra['cost']; ?>" placeholder="Cost">
                                        <input type="text" name="extras[<?php echo $index; ?>][value]" value="<?php echo htmlspecialchars($extra['value']); ?>" placeholder="Value">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="add-btn mt-4" onclick="addExtra()"><i class="fa-regular fa-plus mr-2"></i> Add Extra</button>
                        </div>

                        <!-- Timeframes Tab -->
                        <div id="timeframes" class="tab-content">
                            <h2 class="text-xl font-semibold text-slate-700 mb-4">Configure Timeframes</h2>
                            <div class="space-y-4">
                                <div class="config-item">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Low Complexity (1-4 features)</label>
                                    <input type="text" name="timeframes[low]" value="<?php echo htmlspecialchars($config['timeframes']['low']); ?>" class="w-full border border-slate-200 rounded-lg p-3">
                                </div>
                                <div class="config-item">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Medium Complexity (5-8 features)</label>
                                    <input type="text" name="timeframes[medium]" value="<?php echo htmlspecialchars($config['timeframes']['medium']); ?>" class="w-full border border-slate-200 rounded-lg p-3">
                                </div>
                                <div class="config-item">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">High Complexity (9+ features)</label>
                                    <input type="text" name="timeframes[high]" value="<?php echo htmlspecialchars($config['timeframes']['high']); ?>" class="w-full border border-slate-200 rounded-lg p-3">
                                </div>
                            </div>
                        </div>

                        <!-- Hidden inputs for JSON data -->
                        <input type="hidden" name="sections" id="sections-input">
                        <input type="hidden" name="platforms" id="platforms-input">
                        <input type="hidden" name="features" id="features-input">
                        <input type="hidden" name="extras" id="extras-input">
                        <input type="hidden" name="timeframes" id="timeframes-input">

                        <div class="mt-8 flex justify-end">
                            <button type="submit" name="save_config" class="px-8 py-3 bg-indigo-700 hover:bg-indigo-800 text-white rounded-xl shadow-md flex items-center gap-3 text-lg font-medium transition">
                                <i class="fa-regular fa-floppy-disk"></i> Save Configuration & Generate HTML
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
        // Tab switching
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                tab.classList.add('active');
                const tabId = tab.dataset.tab;
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Add functions
        function addPlatform() {
            const container = document.getElementById('platforms-container');
            const index = container.children.length;
            const html = `
                <div class="config-item">
                    <div class="config-item-header">
                        <span class="font-medium">Platform #${index + 1}</span>
                        <button type="button" class="remove-btn" onclick="removeItem(this, 'platforms')"><i class="fa-regular fa-trash-can"></i></button>
                    </div>
                    <div class="item-row">
                        <input type="text" name="platforms[${index}][label]" placeholder="Label (e.g. Web App)">
                        <input type="number" name="platforms[${index}][cost]" placeholder="Cost">
                        <input type="text" name="platforms[${index}][value]" placeholder="Value">
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }

        function addFeature() {
            const container = document.getElementById('features-container');
            const index = container.children.length;
            const html = `
                <div class="config-item">
                    <div class="config-item-header">
                        <span class="font-medium">Feature #${index + 1}</span>
                        <button type="button" class="remove-btn" onclick="removeItem(this, 'features')"><i class="fa-regular fa-trash-can"></i></button>
                    </div>
                    <div class="item-row">
                        <input type="text" name="features[${index}][label]" placeholder="Label">
                        <input type="number" name="features[${index}][cost]" placeholder="Cost">
                        <input type="text" name="features[${index}][value]" placeholder="Value">
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }

        function addExtra() {
            const container = document.getElementById('extras-container');
            const index = container.children.length;
            const html = `
                <div class="config-item">
                    <div class="config-item-header">
                        <span class="font-medium">Extra #${index + 1}</span>
                        <button type="button" class="remove-btn" onclick="removeItem(this, 'extras')"><i class="fa-regular fa-trash-can"></i></button>
                    </div>
                    <div class="item-row">
                        <input type="text" name="extras[${index}][label]" placeholder="Label">
                        <input type="number" name="extras[${index}][cost]" placeholder="Cost">
                        <input type="text" name="extras[${index}][value]" placeholder="Value">
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }

        function removeItem(btn, type) {
            if (confirm('Are you sure you want to remove this item?')) {
                btn.closest('.config-item').remove();
            }
        }

        // Prepare form data before submit
        document.getElementById('configForm').addEventListener('submit', function(e) {
            // Gather platforms
            const platforms = [];
            document.querySelectorAll('#platforms-container .config-item').forEach(item => {
                const inputs = item.querySelectorAll('input');
                platforms.push({
                    label: inputs[0].value,
                    cost: parseInt(inputs[1].value) || 0,
                    value: inputs[2].value
                });
            });
            document.getElementById('platforms-input').value = JSON.stringify(platforms);

            // Gather features
            const features = [];
            document.querySelectorAll('#features-container .config-item').forEach(item => {
                const inputs = item.querySelectorAll('input');
                features.push({
                    label: inputs[0].value,
                    cost: parseInt(inputs[1].value) || 0,
                    value: inputs[2].value
                });
            });
            document.getElementById('features-input').value = JSON.stringify(features);

            // Gather extras
            const extras = [];
            document.querySelectorAll('#extras-container .config-item').forEach(item => {
                const inputs = item.querySelectorAll('input');
                extras.push({
                    label: inputs[0].value,
                    cost: parseInt(inputs[1].value) || 0,
                    value: inputs[2].value
                });
            });
            document.getElementById('extras-input').value = JSON.stringify(extras);

            // Gather timeframes
            const timeframes = {
                low: document.querySelector('input[name="timeframes[low]"]').value,
                medium: document.querySelector('input[name="timeframes[medium]"]').value,
                high: document.querySelector('input[name="timeframes[high]"]').value
            };
            document.getElementById('timeframes-input').value = JSON.stringify(timeframes);
        });
        <?php endif; ?>
    </script>
</body>
</html>