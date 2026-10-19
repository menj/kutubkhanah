<?php
/**
 * Kutubkhanah: renders the public Contact and Request Access pages.
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Forms
{
    public static function render(string $type): string
    {
        $e = [Library::class, 'e'];
        $enabled = Settings::get($type === 'access' ? 'enable_access' : 'enable_contact');
        if (!$enabled) return '<p class="kk-muted">' . __('This form is not available.') . '</p>';

        $result = null;
        $post = [];
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['kk_form'])) {
            $post = $_POST;
            $result = Messages::submit($type, $post);
            if ($result['ok']) $post = [];
        }
        $errors = $result['errors'] ?? [];
        $token = Messages::token();
        $val = fn($k) => $e($post[$k] ?? '');
        $field = function (string $name, string $label, string $control, bool $required) use ($errors, $e) {
            $err = isset($errors[$name]) ? '<p class="kk-alert kk-alert--err kk-alert--field" id="kk-err-' . $name . '">' . $e($errors[$name]) . '</p>' : '';
            return '<div class="kk-field"><label for="kk-' . $name . '">' . $e($label) . ($required ? ' <span class="kk-req" aria-hidden="true">*</span>' : '') . '</label>'
                . $control . $err . '</div>';
        };
        $input = function (string $name, string $type, string $auto, bool $required, string $placeholder = '') use ($errors, $val, $e) {
            return '<input class="kk-input" id="kk-' . $name . '" name="' . $name . '" type="' . $type . '" value="' . $val($name) . '"'
                . ($auto ? ' autocomplete="' . $auto . '"' : '') . ($required ? ' required' : '')
                . ($placeholder ? ' placeholder="' . $e($placeholder) . '"' : '')
                . (isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="kk-err-' . $name . '"' : '') . '>';
        };
        $area = function (string $name, string $placeholder) use ($errors, $val, $e) {
            return '<textarea class="kk-input" id="kk-' . $name . '" name="' . $name . '" rows="6" required placeholder="' . $e($placeholder) . '"'
                . (isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="kk-err-' . $name . '"' : '') . '>' . $val($name) . '</textarea>';
        };

        $out = '';
        if ($type === 'access') {
            $out .= '<div class="kk-notes kk-form-width"><p class="kk-notes__label">' . __('Please note') . '</p><p class="kk-notes__body">'
                . __('This is a private collection. Visits and loans are by approved request. Please give enough context for your request to be considered.') . '</p></div>';
        } else {
            $out .= '<p class="kk-muted kk-form-width">' . __('Questions about a book, a visit or a suggestion for the collection? Send a message.') . '</p>';
        }
        if ($result) {
            $out .= '<div class="kk-alert kk-form-width ' . ($result['ok'] ? 'kk-alert--ok' : 'kk-alert--err') . '" role="' . ($result['ok'] ? 'status' : 'alert') . '">' . $e($result['message']) . '</div>';
        }

        $out .= '<form class="kk-form" method="post" action="index.php?p=' . ($type === 'access' ? 'request_access' : 'contact') . '" novalidate>'
            . '<input type="hidden" name="kk_form" value="1"><input type="hidden" name="kk_token" value="' . $e($token) . '">'
            . '<div class="kk-hp" aria-hidden="true"><label for="kk-website">Website</label><input id="kk-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>'
            . '<div class="kk-form__row">'
            . $field('name', __('Full name'), $input('name', 'text', 'name', true), true)
            . $field('email', __('Email address'), $input('email', 'email', 'email', true), true)
            . '</div>';
        if ($type === 'access') {
            $out .= $field('affiliation', __('Affiliation'), $input('affiliation', 'text', 'organization', false, __('Organisation, school, or independent reader')), false)
                . $field('reason', __('Reason for access'), $area('reason', __('What would you like to read or see, and why?')), true);
        } else {
            $out .= $field('subject', __('Subject'), $input('subject', 'text', '', true), true)
                . $field('message', __('Message'), $area('message', ''), true);
        }
        $out .= '<button type="submit" class="kk-btn kk-btn--primary">' . ($type === 'access' ? __('Submit request') : __('Send message')) . '</button></form>';
        return $out;
    }
}
