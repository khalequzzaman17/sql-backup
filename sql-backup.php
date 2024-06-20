<?php
/*
Plugin Name: Simple SQL Backup
Plugin URI: https://github.com/khalequzzaman17/sql-backup
Description: This plugin generates SQL backups and provides options to download or delete them.
Version: 1.0
Author: Khalequzzaman
Author URI: https://github.com/khalequzzaman17
*/

// Define plugin constants
define( 'SQL_BACKUP_VERSION', '1.0' );
define( 'SQL_BACKUP_AUTHOR', 'Khalequzzaman' );

// Hook into WordPress activation and deactivation actions
register_activation_hook( __FILE__, 'sql_backup_create_backup_dir' );

// Function to create backup directory on plugin activation
function sql_backup_create_backup_dir() {
    $upload_dir = wp_upload_dir();
    $backup_dir = trailingslashit( $upload_dir['basedir'] ) . 'backup';

    if ( ! file_exists( $backup_dir ) ) {
        mkdir( $backup_dir, 0755 );
    }
}

// Hook into admin menu to add backup SQL option
add_action( 'admin_menu', 'sql_backup_admin_menu' );

// Function to add backup SQL option to admin menu
function sql_backup_admin_menu() {
    add_management_page(
        'Backup SQL',
        'Backup SQL',
        'manage_options',
        'sql_backup',
        'sql_backup_generate_backup_page'
    );
}

// Function to generate backup SQL and display backup page
function sql_backup_generate_backup_page() {
    ?>
    <div class="wrap">
        <h1>Generate SQL Backup</h1>
        <form method="post" action="">
            <input type="hidden" name="sql_backup_generate" value="true">
            <p>Click the button below to generate a backup of your database as an SQL file.</p>
            <p>
                <input type="submit" class="button button-primary" value="Generate Backup">
            </p>
        </form>
    </div>
    <?php

    // Display existing backups with download/delete options
    sql_backup_display_existing_backups();
}

// Handle form submission to generate SQL backup
if ( isset( $_POST['sql_backup_generate'] ) && $_POST['sql_backup_generate'] === 'true' ) {
    $result = sql_backup_generate_sql_backup();
    if ( $result ) {
        echo '<div class="updated"><p>SQL backup generated: <strong>' . $result['filename'] . '</strong></p></div>';
    } else {
        echo '<div class="error"><p>Failed to generate SQL backup.</p></div>';
    }
}

// Function to generate SQL backup
function sql_backup_generate_sql_backup() {
    global $wpdb;
    $backup_dir = sql_backup_get_backup_dir();
    $timestamp = time();
    $backup_time = date( 'Y-m-d_H-i-s', $timestamp );
    $filename = $backup_dir . '/' . $backup_time . '.sql';
    $sql = 'mysqldump --user=' . DB_USER . ' --password=' . DB_PASSWORD . ' --host=' . DB_HOST . ' ' . DB_NAME . ' > ' . $filename;
    exec( $sql );

    if ( file_exists( $filename ) ) {
        return array(
            'filename' => $filename,
            'backup_time' => $backup_time
        );
    } else {
        return false;
    }
}

// Function to display existing backups with download/delete options
function sql_backup_display_existing_backups() {
    $backup_dir = sql_backup_get_backup_dir();
    $backups = scandir( $backup_dir );
    $backups = array_diff( $backups, array( '.', '..' ) );

    if ( empty( $backups ) ) {
        echo '<p>No backups found.</p>';
        return;
    }

    echo '<h2>Existing Backups</h2>';
    echo '<table class="widefat">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Backup Time</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ( $backups as $backup ) {
        $backup_path = $backup_dir . '/' . $backup;
        $backup_time = pathinfo( $backup_path, PATHINFO_FILENAME );

        echo '<tr>';
        echo '<td>' . $backup_time . '</td>';
        echo '<td>';
        echo '<a href="' . admin_url( 'admin-ajax.php?action=sql_backup_download&file=' . urlencode( $backup ) ) . '">Download</a> | ';
        echo '<a href="' . admin_url( 'admin-post.php?action=sql_backup_delete&file=' . urlencode( $backup ) ) . '">Delete</a>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
}

// Hook into AJAX action for downloading backup file
add_action( 'wp_ajax_sql_backup_download', 'sql_backup_ajax_download' );

// Function to handle AJAX download of backup file
function sql_backup_ajax_download() {
    if ( isset( $_GET['file'] ) ) {
        $backup_file = sanitize_text_field( wp_unslash( $_GET['file'] ) );
        $backup_dir = sql_backup_get_backup_dir();
        $backup_path = $backup_dir . '/' . $backup_file;

        if ( file_exists( $backup_path ) ) {
            header( 'Content-Description: File Transfer' );
            header( 'Content-Type: application/octet-stream' );
            header( 'Content-Disposition: attachment; filename="' . basename( $backup_path ) . '"' );
            header( 'Expires: 0' );
            header( 'Cache-Control: must-revalidate' );
            header( 'Pragma: public' );
            header( 'Content-Length: ' . filesize( $backup_path ) );
            readfile( $backup_path );
            exit;
        }
    }
}

// Hook into admin action for deleting backup file
add_action( 'admin_post_sql_backup_delete', 'sql_backup_delete_backup' );

// Function to handle deletion of backup file
function sql_backup_delete_backup() {
    if ( isset( $_GET['file'] ) && current_user_can( 'manage_options' ) ) {
        $backup_file = sanitize_text_field( wp_unslash( $_GET['file'] ) );
        $backup_dir = sql_backup_get_backup_dir();
        $backup_path = $backup_dir . '/' . $backup_file;

        if ( file_exists( $backup_path ) ) {
            unlink( $backup_path );
            $message = 'Backup file ' . $backup_file . ' deleted successfully.';
            set_transient( 'sql_backup_delete_message', $message, 5 ); // Set transient for 5 seconds
            wp_redirect( admin_url( 'tools.php?page=sql_backup' ) ); // Redirect to the correct page
            exit;
        }
    }
}

// Function to get backup directory path
function sql_backup_get_backup_dir() {
    $upload_dir = wp_upload_dir();
    return trailingslashit( $upload_dir['basedir'] ) . 'backup';
}

// Function to display deletion message
add_action( 'admin_notices', 'sql_backup_delete_message_notice' );

function sql_backup_delete_message_notice() {
    $message = get_transient( 'sql_backup_delete_message' );
    if ( $message ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
        delete_transient( 'sql_backup_delete_message' );
    }
}
