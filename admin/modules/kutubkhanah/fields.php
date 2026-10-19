<?php
/**
 * Kutubkhanah: custom fields per format (Signed copy, Condition, Provenance, Issue number…).
 *
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Fields;
use SLiMS\Kutubkhanah\Settings;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

$fields = Fields::all();
$reload = MWB . 'kutubkhanah/fields.php';

function kk_field_form(array $f, string $reload): void
{
    $id = (int)($f['id'] ?? 0);
    $fmts = Fields::formatsOf($f + ['formats' => '']);
    $p = 'kk-fd' . $id . '-';
    echo '<form class="kk-panel kk-save notAJAX" data-action="field_save" data-id="' . $id . '" data-reload="' . kk_e($reload) . '">';
    echo $id ? '' : '<h3>' . __('Add a field') . '</h3>';
    echo '<div class="kk-row"><div class="kk-row__label"><label for="' . $p . 'label">' . __('Name') . '</label></div><div class="kk-row__field">'
        . '<input class="kk-input" id="' . $p . 'label" name="label" value="' . kk_e($f['label'] ?? '') . '" required></div></div>';
    $types = '';
    foreach (Fields::typeLabels() as $k => $l) $types .= '<option value="' . $k . '"' . (($f['type'] ?? 'text') === $k ? ' selected' : '') . '>' . kk_e($l) . '</option>';
    echo '<div class="kk-row"><div class="kk-row__label"><label for="' . $p . 'type">' . __('Type') . '</label></div><div class="kk-row__field">'
        . '<select class="kk-input" id="' . $p . 'type" name="type">' . $types . '</select></div></div>';
    echo '<div class="kk-row"><div class="kk-row__label"><label for="' . $p . 'options">' . __('Choices') . '</label></div><div class="kk-row__field">'
        . '<textarea class="kk-input kk-textarea" id="' . $p . 'options" name="options" rows="3">' . kk_e($f['options'] ?? '') . '</textarea>'
        . '<p class="kk-note">' . __('For "Choice from a list": one choice per line.') . '</p></div></div>';
    $boxes = '';
    foreach (Settings::formats() as $fmt) {
        $boxes .= '<label class="kk-check kk-check--inline"><input type="checkbox" name="fmt_' . md5($fmt) . '" value="1"' . (in_array($fmt, $fmts, true) ? ' checked' : '') . '> ' . kk_e($fmt) . '</label> ';
    }
    echo '<div class="kk-row"><div class="kk-row__label">' . __('Formats') . '</div><div class="kk-row__field">' . $boxes
        . '<p class="kk-note">' . __('Show this field only for these formats. Tick none to show it for every book.') . '</p></div></div>';
    echo '<div class="kk-row"><div class="kk-row__label">' . __('Visibility') . '</div><div class="kk-row__field">'
        . '<label class="kk-check"><input type="checkbox" name="is_public" value="1"' . (!empty($f['is_public']) || !$id ? ' checked' : '') . '> ' . __('Show on the public book page') . '</label></div></div>';
    echo '<div class="kk-row"><div class="kk-row__label"><label for="' . $p . 'sort">' . __('Order') . '</label></div><div class="kk-row__field">'
        . '<input class="kk-input kk-input--short" type="number" id="' . $p . 'sort" name="sort" value="' . (int)($f['sort'] ?? 0) . '"></div></div>';
    echo '<div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary">' . ($id ? __('Save field') : __('Add field')) . '</button>';
    if ($id) {
        echo '<button type="button" class="kk-btn" data-kk-action="field_delete" data-id="' . $id . '" data-confirm="'
            . kk_e(__('Delete this field and the values stored in it?')) . '" data-reload="' . kk_e($reload) . '">' . __('Delete') . '</button>';
    }
    echo '<span class="kk-note kk-saved" aria-live="polite"></span></div></form>';
}

echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Custom Fields') ?></h2>
    <p><?= __('Your own details for each format, such as a signed copy, condition and provenance for books, or the issue number for periodicals. Fill them in on Book Details.') ?></p>
  </header>
  <?php foreach ($fields as $f) kk_field_form($f, $reload); ?>
  <?php kk_field_form([], $reload); ?>
</div>
<?= kk_script('admin.js') ?>
