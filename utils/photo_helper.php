<?php
/**
 * Helper function to get employee profile photo URL
 * @param string $employee_number The employee ID/number
 * @return string The photo URL or empty string if not found
 */
function getEmployeePhotoUrl($employee_number)
{
    if (empty($employee_number)) {
        return '';
    }

    // Base URL for employee photos
    $base_url = 'http://10.2.0.8/lrnph/emp_photos/';

    // Return the first extension format (browser will handle 404 if not found)
    // We'll use JavaScript to handle fallback on client side
    return $base_url . htmlspecialchars($employee_number) . '.jpg';
}

/**
 * Generate an img tag with fallback for employee photo
 * @param string $employee_number The employee ID/number
 * @param string $classes CSS classes for the img tag
 * @param string $alt Alt text (defaults to employee number)
 * @param string $img_attrs Additional img attributes like width="48" style="..."
 * @param string $icon_style Style for the fallback ion-icon
 * @return string HTML img tag with fallback icon
 */
function getEmployeePhotoImg($employee_number, $classes = '', $alt = '', $img_attrs = '', $icon_style = 'font-size: 2.5rem; color: #a1a1aa;')
{
    if (empty($employee_number)) {
        return '<ion-icon name="person-circle-outline" style="' . htmlspecialchars($icon_style) . '"></ion-icon>';
    }

    $employee_id = htmlspecialchars($employee_number);
    $alt_text = $alt ?: 'Employee ' . $employee_id;
    $classes_attr = $classes ? ' class="' . htmlspecialchars($classes) . ' position-relative z-10"' : ' class="position-relative z-10"';

    // Base URL for employee photos
    $base_url = 'http://10.2.0.8/lrnph/emp_photos/';

    // Create img tag with JavaScript fallback that tries multiple extensions
    $js_function = "tryPhotoExtensions('$employee_id', this)";

    return '<div class="position-relative d-flex align-items-center justify-content-center w-100 h-100"><img src="' . $base_url . $employee_id . '.jpg" alt="' . htmlspecialchars($alt_text) . '"' . $classes_attr . ' ' . $img_attrs . ' onerror="' . $js_function . '" /><ion-icon name="person-circle-outline" style="' . htmlspecialchars($icon_style) . '; position: absolute; z-index: 0; display: none;"></ion-icon></div>';
}
?>
