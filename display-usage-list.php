<?php
/*
Plugin Name: Disk Usage List
Description: Displays the storage allocation of the folders in the WordPress root directory in list form.
Version: 0.9
Author: Sascha Liem
*/

if (!defined('ABSPATH')) {
    exit; // Direktzugriff verhindern
}

// Check if "Disk Usage Sunburst" plugin is active
function is_disk_usage_sunburst_active() {
    return in_array('disk-usage-sunburst/rbdusb-disk-usage-sunburst.php', apply_filters('active_plugins', get_option('active_plugins')));
}

// Hook for Admin-Notifications
add_action('admin_notices', 'disk_usage_sunburst_notice');

function disk_usage_sunburst_notice() {
    if (!is_disk_usage_sunburst_active()) {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Das "Disk Usage Sunburst" Plugin muss installiert und aktiviert sein, um das "Disk Usage List" Plugin zu verwenden.', 'disk-usage-list'); ?></p>
        </div>
        <?php
    }
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
    add_menu_page('Disk Usage', 'Disk Usage', 'manage_options', 'disk-usage', 'display_disk_usage');
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
    if (!is_disk_usage_sunburst_active()) {
        echo '<div class="wrap"><h1>Disk Usage</h1><p>Das "Disk Usage Sunburst" is required for this plugin to work.</p></div>';
        return;
    }

    $root_dir = ABSPATH;
    
    if (isset($_GET['folder'])) {
        $requested_dir = sanitize_text_field($_GET['folder']);
        if (strpos($requested_dir, $root_dir) === 0) {
            $root_dir = $requested_dir;
        }
    }

    echo '<div class="wrap">';
    echo '<h1>Disk Usage List</h1>';

    // Generate Report Link and Dropdown
    echo '<p>';
    echo '<select id="size-filter">
            <option value="100">ab 100MB</option>
            <option value="300">ab 300MB</option>
            <option value="500">ab 500MB</option>
            <option value="1000">ab 1GB</option>
          </select> ';
    echo '<button id="generate-report">Bericht generieren</button>';
    echo '</p>';

    // Report Container
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
        
        // Sort the folders by size in the normal view
        arsort($top_level_folders);
        
        foreach ($top_level_folders as $folder => $size) {
            echo '<tr>';
            echo '<td><a href="' . esc_url(admin_url('admin.php?page=disk-usage&folder=' . urlencode($root_dir . '/' . $folder))) . '">' . esc_html($folder) . '</a></td>';
            echo '<td>' . esc_html(format_size($size)) . '</td>';
            echo '</tr>';
        }
    }
    
    echo '</tbody></table>';

    // JavaScript for Report
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#generate-report').click(function(e) {
            e.preventDefault();
            var sizeFilter = $('#size-filter').val() * 1024 * 1024; // Calc in Bytes
            var usage = <?php echo json_encode($usage); ?>;
            var report = '';
            
            Object.keys(usage).sort().forEach(function(folder) {
                if (usage[folder] >= sizeFilter) {
                    report += folder + ' - ' + formatSize(usage[folder]) + '<br>';
                }
            });
            
            if (report === '') {
                report = 'No directories found that match the filter criteria.';
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

// Add CSS for better styling
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
?>
