jQuery(document).ready(function($) {
    if ($('#wprr-csv-file').length === 0) return;

    var sheetsData = {};
    var sheetNames = [];
    var mappings = {};

    var $dropZone = $('#wprr-upload-zone');
    var $fileInput = $('#wprr-csv-file');
    var $mappingSection = $('#wprr-mapping-section');
    var $sheetsContainer = $('#wprr-sheets-container');
    var $eventSelect = $('#wprr-event-select');
    var $processBtn = $('#wprr-process-btn');
    var $messages = $('#wprr-messages');

    // Make drop zone clickable
    $dropZone.on('click', function() {
        $fileInput.click();
    });

    // Drag and drop events
    $dropZone.on('dragover dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).css('background-color', '#f0f0f0');
    }).on('dragleave dragend drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).css('background-color', 'transparent');
    });

    $dropZone.on('drop', function(e) {
        if (e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files.length) {
            $fileInput[0].files = e.originalEvent.dataTransfer.files;
            handleFileSelect();
        }
    });

    $fileInput.on('change', handleFileSelect);

    // Event select change
    $eventSelect.on('change', function() {
        renderMappingUI();
    });

    function showMessage(msg, type) {
        var cls = type === 'error' ? 'notice-error' : 'notice-success';
        $messages.html('<div class="notice ' + cls + ' is-dismissible"><p>' + msg + '</p></div>');
    }

    function handleFileSelect() {
        var file = $fileInput[0].files[0];
        if (!file) return;

        $dropZone.find('h3').text(file.name);
        $messages.empty();

        var reader = new FileReader();
        reader.onload = function(e) {
            try {
                var data = new Uint8Array(e.target.result);
                var workbook = XLSX.read(data, {type: 'array', cellDates: true});
                
                sheetsData = {};
                mappings = {};
                sheetNames = workbook.SheetNames;

                sheetNames.forEach(function(name) {
                    var ws = workbook.Sheets[name];
                    var sheetRows = XLSX.utils.sheet_to_json(ws, {header: 1, raw: false, dateNF: "hh:mm:ss", defval: ""});
                    
                    if (sheetRows.length === 0) return;
                    
                    // Dynamic Header Detection: Find the row with the most non-empty columns, or one with keywords
                    var headerRowIndex = 0;
                    var maxCols = 0;
                    
                    for (var i = 0; i < Math.min(50, sheetRows.length); i++) {
                        var row = sheetRows[i];
                        var nonEmpties = row.filter(function(c) { return String(c).trim() !== ''; }).length;
                        
                        var rowStr = row.join(' ').toLowerCase();
                        // Only flag as keyword match if it really looks like a race result header
                        var hasKeywords = (rowStr.includes('bib') || rowStr.includes('name') || rowStr.includes('participant')) 
                                          && (rowStr.includes('time') || rowStr.includes('chip') || rowStr.includes('net') || rowStr.includes('gender'));
                        
                        if (hasKeywords && nonEmpties >= 3) {
                            headerRowIndex = i;
                            break;
                        } else if (nonEmpties > maxCols) {
                            headerRowIndex = i;
                            maxCols = nonEmpties;
                        }
                    }
                    
                    var headers = (sheetRows[headerRowIndex] || []).map(String).map(function(s) { return s.trim(); });
                    
                    // Data starts after the detected header row
                    sheetsData[name] = sheetRows.slice(headerRowIndex + 1);
                    
                    mappings[name] = {
                        categoryId: '',
                        bibCol: headers.find(h => h.toLowerCase().includes('bib')) || '',
                        nameCol: headers.find(h => h.toLowerCase().includes('name') || h.toLowerCase().includes('participant') || h.toLowerCase().includes('runner')) || '',
                        genderCol: headers.find(h => h.toLowerCase().includes('gender') || h.toLowerCase().includes('sex') || h.toLowerCase() === 'sx') || '',
                        chipCol: headers.find(h => h.toLowerCase().includes('chip') || h.toLowerCase().includes('net') || h.toLowerCase().includes('time')) || '',
                        gunCol: headers.find(h => h.toLowerCase().includes('gun') || h.toLowerCase().includes('gross')) || '',
                        availableHeaders: headers.filter(h => h !== '')
                    };
                });

                $mappingSection.show();
                renderMappingUI();
            } catch (err) {
                showMessage('Error parsing Excel file. Please ensure it is a valid format.', 'error');
                console.error(err);
            }
        };
        reader.readAsArrayBuffer(file);
    }

    function renderMappingUI() {
        $sheetsContainer.empty();
        
        var selectedEventId = $eventSelect.val();
        var selectedEvent = null;
        
        if (selectedEventId) {
            selectedEvent = window.wprrEventsData.find(e => e.id == selectedEventId);
        }

        var distances = selectedEvent ? selectedEvent.categories : [];

        sheetNames.forEach(function(sheetName) {
            var map = mappings[sheetName];
            if (!map) return;
            
            var headers = map.availableHeaders || [];
            
            var $panel = $('<div class="wprr-sheet-panel"></div>');
            
            var $header = $('<div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #ccc; padding-bottom:10px;"></div>');
            $header.append('<h4>Sheet: <strong>' + sheetName + '</strong> (' + (sheetsData[sheetName]?.length || 0) + ' rows)</h4>');
            
            var $targetSelect = $('<select class="wprr-target-cat" data-sheet="' + sheetName + '"><option value="">-- Do not import this sheet --</option></select>');
            distances.forEach(function(dist) {
                var selected = map.categoryId === dist ? 'selected' : '';
                $targetSelect.append('<option value="' + dist + '" ' + selected + '>' + dist + '</option>');
            });
            
            var $targetWrap = $('<div><label>Target Distance: </label></div>');
            $targetWrap.append($targetSelect);
            $header.append($targetWrap);
            $panel.append($header);
            
            var $grid = $('<div class="wprr-mapping-grid"></div>');
            
            // Generate field mapping dropdowns
            var fields = [
                { id: 'bibCol', label: 'Bib' },
                { id: 'nameCol', label: 'Name' },
                { id: 'genderCol', label: 'Gender' },
                { id: 'chipCol', label: 'Chip Time' },
                { id: 'gunCol', label: 'Gun Time' }
            ];
            
            fields.forEach(function(f) {
                var $item = $('<div class="wprr-mapping-item"></div>');
                $item.append('<label>' + f.label + '</label><br>');
                
                var $sel = $('<select class="wprr-field-map" data-sheet="' + sheetName + '" data-field="' + f.id + '"><option value="">Select Column</option></select>');
                headers.forEach(function(h) {
                    var selected = map[f.id] === h ? 'selected' : '';
                    $sel.append('<option value="' + h + '" ' + selected + '>' + h + '</option>');
                });
                
                $item.append($sel);
                $grid.append($item);
            });
            
            // Only show grid if a distance is selected
            if (!map.categoryId) {
                $grid.hide();
            }
            
            $panel.append($grid);
            $sheetsContainer.append($panel);
        });
    }

    $sheetsContainer.on('change', '.wprr-target-cat', function() {
        var sheetName = $(this).data('sheet');
        mappings[sheetName].categoryId = $(this).val();
        
        var $grid = $(this).closest('.wprr-sheet-panel').find('.wprr-mapping-grid');
        if (mappings[sheetName].categoryId) {
            $grid.show();
        } else {
            $grid.hide();
        }
    });

    $sheetsContainer.on('change', '.wprr-field-map', function() {
        var sheetName = $(this).data('sheet');
        var field = $(this).data('field');
        mappings[sheetName][field] = $(this).val();
    });

    function formatExcelTime(val) {
        if (!val) return '';
        if (typeof val === 'number' && val > 0 && val < 1) {
            var totalSeconds = Math.round(val * 24 * 60 * 60);
            var hours = Math.floor(totalSeconds / 3600);
            var minutes = Math.floor((totalSeconds % 3600) / 60);
            var seconds = totalSeconds % 60;
            
            var hh = String(hours).padStart(2, '0');
            var mm = String(minutes).padStart(2, '0');
            var ss = String(seconds).padStart(2, '0');
            
            return hh + ':' + mm + ':' + ss;
        }
        
        // Ensure any existing string format like "1:23:45" is padded to "01:23:45"
        var parts = String(val).trim().split(':');
        if (parts.length === 3 && parts[0].length === 1) {
            parts[0] = '0' + parts[0];
            return parts.join(':');
        }
        
        return String(val).trim();
    }

    $processBtn.on('click', function(e) {
        e.preventDefault();
        
        var eventId = $eventSelect.val();
        if (!eventId) {
            showMessage('Please select an event first.', 'error');
            return;
        }

        var finalResults = [];
        var replaceData = $('#wprr-replace-data').is(':checked');
        
        try {
            sheetNames.forEach(function(sheetName) {
                var map = mappings[sheetName];
                if (!map.categoryId) return; // Skip unmapped
                
                if (!map.bibCol || !map.nameCol || !map.genderCol || !map.chipCol) {
                    throw new Error('Please map all required columns for sheet: ' + sheetName);
                }

                var data = sheetsData[sheetName];
                var headers = map.availableHeaders;
                var bibIdx = headers.indexOf(map.bibCol);
                var nameIdx = headers.indexOf(map.nameCol);
                var genderIdx = headers.indexOf(map.genderCol);
                var chipIdx = headers.indexOf(map.chipCol);
                var gunIdx = headers.indexOf(map.gunCol);
                
                data.forEach(function(row) {
                    if (row[bibIdx] && row[nameIdx] && row[chipIdx]) {
                        finalResults.push({
                            distance: map.categoryId,
                            bib_number: String(row[bibIdx]).trim(),
                            full_name: String(row[nameIdx]).trim(),
                            gender: String(row[genderIdx] || 'Unknown').trim(),
                            chip_time: formatExcelTime(row[chipIdx]),
                            gun_time: (gunIdx >= 0 && row[gunIdx]) ? formatExcelTime(row[gunIdx]) : ''
                        });
                    }
                });
            });

            if (finalResults.length === 0) {
                throw new Error('No valid records found to upload. Please check your column mappings.');
            }

            var btnText = $processBtn.text();
            $processBtn.text('Processing...').prop('disabled', true);
            $messages.empty();

            $.ajax({
                url: wprr_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wprr_process_mapped_results',
                    nonce: wprr_ajax.nonce,
                    event_id: eventId,
                    replace_data: replaceData ? 1 : 0,
                    results: JSON.stringify(finalResults)
                },
                success: function(response) {
                    $processBtn.text(btnText).prop('disabled', false);
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                        setTimeout(function() {
                            window.location.href = 'admin.php?page=wp_race_results_results';
                        }, 2000);
                    } else {
                        showMessage(response.data || 'Unknown error occurred', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    $processBtn.text(btnText).prop('disabled', false);
                    showMessage('AJAX Error: ' + error, 'error');
                }
            });

        } catch (err) {
            showMessage(err.message, 'error');
        }
    });

});
