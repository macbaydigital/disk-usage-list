<?php
/*
Plugin Name: Disk Usage List
Description: Zeigt die Speicherbelegung der Ordner im WordPress-Wurzelverzeichnis in Listenform an.
Version: 1.0
Author: Macbay Digital
*/

if (!defined('ABSPATH')) {
    exit; // Direktzugriff verhindern
}

function list_disk_usage($dir, $base_path = '') {
    $result = array();

    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $path = $dir . '/' . $file;
                $relative_path = $base_path ? $base_path . '/' . $file : $file;
                if (is_dir($path)) {
                    $size = disk_usage($path);
                    $result[$relative_path] = $size;
                    $result = array_merge($result, list_disk_usage($path, $relative_path));
                }
            }
        }
    }
    return $result;
}

function disk_usage($dir) {
    $size = 0;
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    $size += disk_usage($path);
                } else {
                    $size += filesize($path);
                }
            }
        }
    }
    return $size;
}

add_action('admin_menu', 'custom_disk_usage_menu');

function custom_disk_usage_menu() {
    add_menu_page('Disk Usage', 'Disk Usage', 'manage_options', 'disk-usage', 'display_disk_usage', 'dashicons-chart-pie');
}

function format_size($size) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = 0;
    while ($size >= 1024 && $i < 4) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

function display_disk_usage() {
    $root_dir = ABSPATH;
    
    if (isset($_GET['folder'])) {
        $requested_dir = sanitize_text_field($_GET['folder']);
        if (strpos($requested_dir, $root_dir) === 0) {
            $root_dir = $requested_dir;
        }
    }

    echo '<div class="wrap">';
    echo '<h1>Speicherbelegung im WordPress-Verzeichnis</h1>';

    // Gesamtspeicherplatz des aktuellen Verzeichnisses
    $total_size = disk_usage($root_dir);
    echo '<p><strong>Gesamtspeicherplatz des aktuellen Verzeichnisses:</strong> ' . esc_html(format_size($total_size)) . '</p>';

    // Bericht generieren Link und Dropdown
    echo '<p>';
    echo '<select id="size-filter">
            <option value="100">ab 100MB</option>
            <option value="300">ab 300MB</option>
            <option value="500">ab 500MB</option>
            <option value="1000">ab 1GB</option>
          </select> ';
    echo '<button id="generate-report">Bericht generieren</button>';
    echo '</p>';

    // Bericht Container
    echo '<div id="report-container" style="display:none;">
            <h3>Bericht der größten Verzeichnisse <a href="#" id="close-report">(Schließen)</a></h3>
            <div id="report-content"></div>
          </div>';
    
    if ($root_dir !== ABSPATH) {
        $parent_dir = dirname($root_dir);
        echo '<a href="' . esc_url(admin_url('admin.php?page=disk-usage&folder=' . urlencode($parent_dir))) . '">Zurück zum übergeordneten Verzeichnis</a><br><br>';
    }

    $usage = list_disk_usage($root_dir);
    
    echo '<table class="widefat">';
    echo '<thead><tr><th>Ordner</th><th>Größe</th></tr></thead>';
    echo '<tbody>';
    
    if (empty($usage)) {
        echo '<tr><td colspan="2">Keine Unterordner gefunden in: ' . esc_html($root_dir) . '</td></tr>';
    } else {
        $top_level_folders = array();
        foreach ($usage as $folder => $size) {
            if (dirname($folder) == '.') {
                $top_level_folders[$folder] = $size;
            }
        }
        
        // Sortiere die Ordner nach Größe (absteigend)
        arsort($top_level_folders);
        
        foreach ($top_level_folders as $folder => $size) {
            echo '<tr>';
            echo '<td><a href="' . esc_url(admin_url('admin.php?page=disk-usage&folder=' . urlencode($root_dir . '/' . $folder))) . '">' . esc_html($folder) . '</a></td>';
            echo '<td>' . esc_html(format_size($size)) . '</td>';
            echo '</tr>';
        }
    }
    
    echo '</tbody></table>';

    // JavaScript für den Bericht
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#generate-report').click(function(e) {
            e.preventDefault();
            var sizeFilter = $('#size-filter').val() * 1024 * 1024; // Umrechnung in Bytes
            var usage = <?php echo json_encode($usage); ?>;
            var report = '';
            
            Object.keys(usage).sort(function(a, b) {
                return usage[b] - usage[a];
            }).forEach(function(folder) {
                if (usage[folder] >= sizeFilter) {
                    report += folder + ' - ' + formatSize(usage[folder]) + '<br>';
                }
            });
            
            if (report === '') {
                report = 'Keine Verzeichnisse gefunden, die dem Filterkriterium entsprechen.';
            }
            
            $('#report-content').html(report);
            $('#report-container').show();
        });

        $('#close-report').click(function(e) {
            e.preventDefault();
            $('#report-container').hide();
        });

        function formatSize(bytes) {
            var units = ['B', 'KB', 'MB', 'GB', 'TB'];
            var i = 0;
            while (bytes >= 1024 && i < 4) {
                bytes /= 1024;
                i++;
            }
            return bytes.toFixed(2) + ' ' + units[i];
        }
    });
    </script>
    <?php
    echo '</div>';
}

// Füge CSS für besseres Styling hinzu
add_action('admin_head', 'disk_usage_custom_css');
function disk_usage_custom_css() {
    echo '<style>
        #report-container {
            background-color: #f1f1f1;
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 20px;
        }
        #report-container h3 {
            margin-top: 0;
        }
        #report-content {
            max-height: 300px;
            overflow-y: auto;
        }
        #size-filter {
            margin-right: 10px;
        }
    </style>';
}
