<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv2 (or at your option, any later version).
 * @package Zikula
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

/**
 * Date input for pnForms
 *
 * The date input plugin is a text input plugin that only allows dates to be posted. The value
 * returned from {@link pnForm::pnFormGetValues()} is although a string of the format 'YYYY-MM-DD'
 * since this is the standard internal Zikula format for dates.
 *
 * You can also use all of the features from the pnFormTextInput plugin since the date input
 * inherits from it.
 */
class Form_Plugin_DateInput extends Form_Plugin_TextInput
{
    /**
     * Enable or disable input of time in addition to the date
     * @var bool
     */
    protected $includeTime;
    protected $initDate;
    protected $ifFormat;

    /**
     * Default date value
     *
     * This parameter enables the input to be pre-filled with the current date or similar other well defined
     * default values.
     * You can set the default value to be one of the following:
     * - now: current date and time
     * - today: current date
     * - monthstart: first day in current month
     * - monthend: last day in current month
     * - yearstart: first day in the year
     * - yearend: last day in the year
     * - custom: inital Date
     */
    protected $defaultValue;

    /**
     * Enable or disable selection only mode (with hidden input field), defaults to false
     * @var bool
     */
    protected $useSelectionMode;

    function getFilename()
    {
        return __FILE__;
    }

    function create(&$render, &$params)
    {
        $this->includeTime = (array_key_exists('includeTime', $params) ? $params['includeTime'] : 0);
        $this->daFormat = (array_key_exists('daFormat', $params) ? $params['daFormat'] : ($this->includeTime ? __('%A, %B %d, %Y - %I:%M %p') : __('%A, %B %d, %Y')));
        $this->defaultValue = (array_key_exists('defaultValue', $params) ? $params['defaultValue'] : null);
        $this->initDate = (array_key_exists('initDate', $params) ? $params['initDate'] : 0);
        $this->useSelectionMode = (array_key_exists('useSelectionMode', $params) ? $params['useSelectionMode'] : 0);
        $this->maxLength = ($this->includeTime ? 19 : 12);
        $params['width'] = ($this->includeTime ? '10em' : '8em');

        parent::create($render, $params);

        $this->cssClass .= ' date';
    }

    function render(&$render)
    {
        static $firstTime = true;

        $i18n = & ZI18n::getInstance();

        if (!empty($this->defaultValue) && !$render->IsPostBack()) {
            $d = strtolower($this->defaultValue);
            $now = getdate();
            $date = null;

            if ($d == 'now') {
                $date = time();
            } else if ($d == 'today') {
                $date = mktime(0, 0, 0, $now['mon'], $now['mday'], $now['year']);
            } else if ($d == 'monthstart') {
                $date = mktime(0, 0, 0, $now['mon'], 1, $now['year']);
            } else if ($d == 'monthend') {
                $daysInMonth = date('t');
                $date = mktime(0, 0, 0, $now['mon'], $daysInMonth, $now['year']);
            } else if ($d == 'yearstart') {
                $date = mktime(0, 0, 0, 1, 1, $now['year']);
            } else if ($d == 'yearend') {
                $date = mktime(0, 0, 0, 12, 31, $now['year']);
            } else if ($d == 'custom') {
                $date = strtotime($this->initDate);
            }

            if ($date != null) {
                $this->text = DateUtil::getDatetime($date, ($this->includeTime ? __('%Y-%m-%d %H:%M') : __('%Y-%m-%d')));
            } else {
                $this->text = __('Unknown date');
            }
        }

        if ($firstTime) {
            $lang = ZLanguage::transformFS(ZLanguage::getLanguageCode());
            // map of the jscalendar supported languages
            $map = array(
                'ca' => 'ca_ES',
                'cz' => 'cs_CZ',
                'da' => 'da_DK',
                'de' => 'de_DE',
                'el' => 'el_GR',
                'en-us' => 'en_US',
                'es' => 'es_ES',
                'fi' => 'fi_FI',
                'fr' => 'fr_FR',
                'he' => 'he_IL',
                'hr' => 'hr_HR',
                'hu' => 'hu_HU',
                'it' => 'it_IT',
                'ja' => 'ja_JP',
                'ko' => 'ko_KR',
                'lt' => 'lt_LT',
                'lv' => 'lv_LV',
                'nl' => 'nl_NL',
                'no' => 'no_NO',
                'pl' => 'pl_PL',
                'pt' => 'pt_BR',
                'ro' => 'ro_RO',
                'ru' => 'ru_RU',
                'si' => 'si_SL',
                'sk' => 'sk_SK',
                'sv' => 'sv_SE',
                'tr' => 'tr_TR');

            if (isset($map[$lang])) {
                $lang = $map[$lang];
            }

            $headers[] = 'javascript/jscalendar/calendar.js';
            if (file_exists("javascript/jscalendar/lang/calendar-$lang.utf8.js")) {
                $headers[] = "javascript/jscalendar/lang/calendar-$lang.utf8.js";
            }
            $headers[] = 'javascript/jscalendar/calendar-setup.js';
            PageUtil::addVar('stylesheet', 'javascript/jscalendar/calendar-win2k-cold-2.css');
            PageUtil::addVar('javascript', $headers);
        }
        $firstTime = false;

        $result = '';

        if ($this->useSelectionMode) {
            $hiddenInputField = str_replace(array(
                'type="text"',
                '&nbsp;*'), array(
                'type="hidden"',
                ''), parent::render($render));
            $result .= $hiddenInputField . '<span id="' . $this->id . 'cal" style="background-color: #ff8; cursor: default;" onmouseover="this.style.backgroundColor=\'#ff0\';" onmouseout="this.style.backgroundColor=\'#ff8\';">';
            if ($this->text) {
                $result .= DataUtil::formatForDisplay(DateUtil::getDatetime(DateUtil::parseUIDate($this->text), $this->daFormat));
            } else {
                $result .= __('Select date');
            }
            $result .= '</span>';
            if ($this->mandatory) {
                $result .= '<span class="z-mandatorysym">*</span>';
            }
        } else {
            $result .= '<span class="date" style="white-space: nowrap">';
            $result .= parent::render($render);

            $txt = __('Select date');
            $result .= " <img id=\"{$this->id}_img\" src=\"javascript/jscalendar/img.gif\" style=\"vertical-align: middle\" class=\"clickable\" alt=\"$txt\" /></span>";
        }

        // build jsCalendar script options
        $result .= "<script type=\"text/javascript\">
            // <![CDATA[
            Calendar.setup(
            {
                inputField : \"{$this->id}\",";

        if ($this->includeTime) {
            $this->initDate = str_replace('-', ',', $this->initDate);
            $result .= "
                    ifFormat : \"" . __('%Y-%m-%d %H:%M') . "\",
                    showsTime      :    true,
                    timeFormat     :    \"" . $i18n->locale->getTimeformat() . "\",
                    singleClick    :    false,";
        } else {
            $result .= "
                    ifFormat : \"" . __('%Y-%m-%d') . "\",";
        }

        if ($this->useSelectionMode) {
            $result .= "
                    displayArea :    \"" . $this->id . "cal\",
                    daFormat    :    \"" . $this->daFormat . "\",
                    align       :    \"Bl\",
                    singleClick :    true,";
        } else {
            $result .= "
                    button : \"{$this->id}_img\",";
        }

        $result .= "
                    firstDay: " . $i18n->locale->getFirstweekday() . "
                }
            );
            // ]]>
            </script>";

        return $result;
    }

    function parseValue(&$render, $text)
    {
        if (empty($text)) {
            return null;
        }
        return $text;
    }

    function validate(&$render)
    {
        parent::validate($render);
        if (!$this->isValid) {
            return;
        }

        if (strlen($this->text) > 0) {
            if ($this->includeTime) {
                $dateValue = DateUtil::transformInternalDateTime(DateUtil::parseUIDate($this->text));
            } else {
                $dateValue = DateUtil::transformInternalDate(DateUtil::parseUIDate($this->text));
            }

            if ($dateValue == null) {
                $this->setError(__('Error! Invalid date.'));
            } else {
                // the date validated so we can use the transformed date
                $this->text = $dateValue;
            }
        }
    }

    function formatValue(&$render, $value)
    {
        return DateUtil::formatDatetime($value, ($this->includeTime ? __('%Y-%m-%d %H:%M') : __('%Y-%m-%d')), false);
    }
}
