<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CE_Registry {

    protected static $emails = [];

    public static function boot() {
        self::register_defaults();
        do_action( 'ce_register_emails' );
    }

    public static function register( $id, $args ) {
        self::$emails[ $id ] = wp_parse_args( $args, [
            'id'               => $id,
            'source'           => 'core',
            'label'            => $id,
            'description'      => '',
            'default_subject'  => '',
            'default_body'     => '',
            'available_tokens' => [],
        ] );
    }

    public static function get( $id ) { return self::$emails[ $id ] ?? null; }
    public static function all() { return self::$emails; }

    protected static function register_defaults() {
        self::register( 'core_password_reset', [
            'source' => 'core', 'label' => 'Password reset',
            'description' => 'Sent when a user requests a password reset.',
            'default_subject' => '[{site_name}] Password Reset',
            'default_body' => "Someone has requested a password reset for {user_login}.\n\nIf this was a mistake, ignore this email.\n\nTo reset your password visit:\n{reset_url}\n",
            'available_tokens' => [ '{site_name}' => 'Site name', '{site_url}' => 'Site URL', '{user_login}' => 'Username', '{user_email}' => 'Email', '{reset_url}' => 'Reset link' ],
        ] );
        self::register( 'core_new_user_user', [
            'source' => 'core', 'label' => 'New user - to user',
            'description' => 'Welcome email to a newly registered user.',
            'default_subject' => '[{site_name}] Your new account',
            'default_body' => "Welcome to {site_name}!\n\nUsername: {user_login}\nLogin: {login_url}\n",
            'available_tokens' => [ '{site_name}' => 'Site name', '{user_login}' => 'Username', '{user_email}' => 'Email', '{login_url}' => 'Login URL' ],
        ] );
        self::register( 'core_new_user_admin', [
            'source' => 'core', 'label' => 'New user - to admin',
            'description' => 'Admin notification on new user registration.',
            'default_subject' => '[{site_name}] New User Registration',
            'default_body' => "New user on {site_name}:\n\nUsername: {user_login}\nEmail: {user_email}\n",
            'available_tokens' => [ '{site_name}' => 'Site name', '{user_login}' => 'Username', '{user_email}' => 'Email' ],
        ] );
        self::register( 'core_password_change', [
            'source' => 'core', 'label' => 'Password changed notice (admin)',
            'description' => 'Notification to admin that a user changed their password.',
            'default_subject' => '[{site_name}] Password Changed',
            'default_body' => "Password for {user_login} has been changed.\n",
            'available_tokens' => [ '{site_name}' => 'Site name', '{user_login}' => 'Username', '{user_email}' => 'Email' ],
        ] );
        self::register( 'core_email_change', [
            'source' => 'core', 'label' => 'Email changed notice (user)',
            'description' => 'Notification to user that their email was changed.',
            'default_subject' => '[{site_name}] Notice of Email Change',
            'default_body' => "Hi {user_login},\n\nYour email on {site_name} was changed.\n",
            'available_tokens' => [ '{site_name}' => 'Site name', '{user_login}' => 'Username', '{user_email}' => 'New email' ],
        ] );
        self::register( 'core_comment_notification', [
            'source' => 'core', 'label' => 'New comment notification',
            'description' => 'Sent to post author when a new comment is posted.',
            'default_subject' => '[{site_name}] Comment on {post_title}',
            'default_body' => "New comment on {post_title} by {comment_author}:\n\n{comment_content}\n\nView: {comment_url}\n",
            'available_tokens' => [ '{site_name}' => 'Site name', '{post_title}' => 'Post title', '{comment_author}' => 'Author', '{comment_content}' => 'Content', '{comment_url}' => 'Permalink' ],
        ] );
        self::register( 'core_comment_moderation', [
            'source' => 'core', 'label' => 'Comment moderation notice',
            'description' => 'Sent to moderator when a comment awaits moderation.',
            'default_subject' => '[{site_name}] Please moderate: {post_title}',
            'default_body' => "A comment by {comment_author} is awaiting moderation on {post_title}:\n\n{comment_content}\n\nModerate: {moderation_url}\n",
            'available_tokens' => [ '{site_name}' => 'Site name', '{post_title}' => 'Post title', '{comment_author}' => 'Author', '{comment_content}' => 'Content', '{moderation_url}' => 'Moderation URL' ],
        ] );
        self::register( 'bbp_subscription_topic', [
            'source' => 'bbp', 'label' => 'bbPress - reply in subscribed topic',
            'description' => 'Sent to topic subscribers when a reply is posted.',
            'default_subject' => '[{site_name}] {topic_title}',
            'default_body' => "{reply_author} wrote:\n\n{reply_content}\n\nView: {reply_url}\n\nUnsubscribe: {unsubscribe_url}\n",
            'available_tokens' => [ '{site_name}' => 'Site name', '{topic_title}' => 'Topic', '{reply_author}' => 'Author', '{reply_content}' => 'Content', '{reply_url}' => 'URL', '{unsubscribe_url}' => 'Unsubscribe' ],
        ] );
        self::register( 'bbp_subscription_forum', [
            'source' => 'bbp', 'label' => 'bbPress - new topic in subscribed forum',
            'description' => 'Sent to forum subscribers when a new topic is created.',
            'default_subject' => '[{site_name}] {topic_title}',
            'default_body' => "{topic_author} started a topic:\n\n{topic_content}\n\nView: {topic_url}\n",
            'available_tokens' => [ '{site_name}' => 'Site name', '{topic_title}' => 'Topic', '{topic_author}' => 'Author', '{topic_content}' => 'Content', '{topic_url}' => 'URL' ],
        ] );
    }
}
