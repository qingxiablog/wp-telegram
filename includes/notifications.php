<?php
/**
 * Notifications Module
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'wp_mail', 'wptg_free_cn_notify_handle_wp_mail', 5, 1 );

function wptg_free_cn_notify_handle_wp_mail( $args ) {
    $bot_token = wptg_free_cn_get_option( 'bot_token' );
    if ( empty( $bot_token ) ) {
        return $args;
    }

    $notify_options = wptg_free_cn_get_option( 'notify', array() );
    $watch_emails   = '';
    if ( ! empty( $notify_options['watch_emails'] ) ) {
        $watch_emails = strtolower( $notify_options['watch_emails'] );
    } elseif ( ! empty( $notify_options['emails'] ) ) {
        $watch_emails = strtolower( $notify_options['emails'] );
    }

    $chat_ids           = ! empty( $notify_options['chat_ids'] ) ? (array) $notify_options['chat_ids'] : array();
    $user_notifications = ! empty( $notify_options['user_notifications'] );

    if ( ! apply_filters( 'wptg_free_cn_notify_send_notification', true, $args ) ) {
        return $args;
    }

    $recipients = is_array( $args['to'] ) ? $args['to'] : explode( ',', $args['to'] );
    $emails_to_notify = array();
    $no_chat_emails = array();
    
    $watch_emails_array = array_map( 'trim', explode( ',', $watch_emails ) );

    foreach ( $recipients as $recipient ) {
        $email = $recipient;
        if ( preg_match( '/<(.*)>/', $recipient, $matches ) ) {
            $email = $matches[1];
        }
        $email = trim( strtolower( $email ) );

        $matched_chat_ids = array();
        
        if ( $user_notifications ) {
            $user = get_user_by( 'email', $email );
            if ( $user ) {
                $user_chat_id = get_user_meta( $user->ID, 'wptelegram_user_id', true );
                if ( $user_chat_id ) {
                    $matched_chat_ids[] = $user_chat_id;
                }
            }
        }

        if ( empty( $matched_chat_ids ) ) {
            if ( $watch_emails === 'any' || in_array( $email, $watch_emails_array, true ) ) {
                $matched_chat_ids = $chat_ids;
            }
        }

        if ( ! empty( $matched_chat_ids ) ) {
            $emails_to_notify[ $email ] = $matched_chat_ids;
        } else {
            $no_chat_emails[] = $recipient;
        }
    }

    if ( empty( $emails_to_notify ) ) {
        return $args;
    }

    $subject = $args['subject'];
    $message = $args['message'];
    
    // Handle quoted-printable
    if ( isset( $args['headers'] ) ) {
        $headers = is_array( $args['headers'] ) ? implode( "\n", $args['headers'] ) : $args['headers'];
        if ( stripos( $headers, 'Content-Transfer-Encoding: quoted-printable' ) !== false ) {
            $message = quoted_printable_decode( $message );
        }
    }

    $template = ! empty( $notify_options['message_template'] ) ? $notify_options['message_template'] : ( ! empty( $notify_options['template'] ) ? $notify_options['template'] : "🔔‌<b>{email_subject}</b>🔔\n\n{email_message}" );
    
    $text = str_replace( 
        array( '{email_subject}', '{email_message}' ),
        array( $subject, $message ),
        $template
    );
    
    $raw_parse_mode = isset( $notify_options['parse_mode'] ) ? $notify_options['parse_mode'] : 'HTML';
    $parse_mode     = wptg_free_cn_valid_parse_mode( $raw_parse_mode );
    if ( function_exists( 'wptg_free_cn_prepare_content' ) ) {
        $text = wptg_free_cn_prepare_content( $text, array( 'format_to' => $parse_mode ) );
    }

    $api = new \WPTelegram\BotAPI\API( $bot_token );

    foreach ( $emails_to_notify as $email => $chats ) {
        foreach ( $chats as $chat ) {
            $chat = explode( '|', $chat )[0]; // Strip note after |
            $parts = explode( ':', $chat );
            $chat_id = $parts[0];
            $message_thread_id = isset( $parts[1] ) ? $parts[1] : null;

            $params = array(
                'chat_id' => $chat_id,
                'text' => $text,
                'parse_mode' => $parse_mode,
                'disable_web_page_preview' => true,
            );
            
            if ( $message_thread_id ) {
                $params['message_thread_id'] = $message_thread_id;
            }
            
            try {
                $api->sendMessage( $params );
            } catch ( Exception $e ) {
                // Ignore
            }
        }
    }

    if ( apply_filters( 'wptg_free_cn_notify_abort_email', false, $args ) ) {
        $args['to'] = implode( ',', $no_chat_emails );
    }

    return $args;
}
