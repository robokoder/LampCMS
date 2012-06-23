<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is licensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 *       the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attributes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2012 (or current year) Dmitri Snytkine
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms\Locale;
/**
 * Class for setting
 * Locate value,
 * and for settings SESSION['langs'] drop-down menu
 *
 * @todo Pass only Viewer and Ini to constructor, do not
 * pass Registry and not necessary to extend LampcmsObject
 *
 * @author Dmitri Snytkine
 *
 */
class Locale extends \Lampcms\LampcmsObject
{
    /**
     * Value of locale used in this object
     *
     * @var string
     */
    protected $locale;

    protected $Registry;


    public function __construct(\Lampcms\Registry $o)
    {
        $this->Registry = $o;
        $this->init();
    }


    /**
     * Sets up the $this->locale value
     * Viewer object should already be instantiated
     * before this method is called
     * This method is usually called from the constructor,
     * so it's really important that this object
     * is NOT requested from the Registry
     * before the Viewer object is instantiated.
     *
     *
     * Get value of locale
     * This method will also
     * set the value in $_SESSION['locale']
     * if $_SESSION is present and
     * locale is not already set in session
     */
    public function init()
    {
        if (!empty($_SESSION) && !empty($_SESSION['locale'])) {
            $this->locale = $_SESSION['locale'];
        } else {

            /**
             * If Viewer is not a guest then
             * get value of locale from Viewer object
             */
            if (!$this->Registry->Viewer->isGuest()) {
                $this->locale = $this->Registry->Viewer->offsetGet('locale');
            } elseif (isset($_COOKIE) && !empty($_COOKIE['locale'])) {
                $this->locale = $_COOKIE['locale'];
            }

            if (empty($locale)) {
                $this->locale = LAMPCMS_DEFAULT_LOCALE;
            }

            if (isset($_SESSION)) {
                $_SESSION['locale'] = $this->locale;
            }
        }

        return $this;
    }


    /**
     * Factory method
     * This is the preferred method
     * for instantiating this class
     *
     * @todo return a sub-class that uses
     * php's Locale class if php has intl extension loaded
     *
     * @param \Lampcms\Registry $o
     */
    public static function factory(\Lampcms\Registry $o)
    {

        return new self($o);
    }


    /**
     * Make html for the "Select language"
     * drop-down menu
     * A sub-class that makes use
     * of php's intl extension may override this
     * and actually translate values of languages
     * into language of current locale
     *
     * @return string
     */
    public function makeOptions()
    {
        $a = $this->Registry->Ini['LOCALES'];
        $tplWrapper = '<div class="fr langs" id="id_langs"><div class="fl icn globe"></div>
		%s</div>';
        $tpl = '<option class="fl" value="%s"%s>%s</option>';
        $ret = '';

        /**
         * If LOCALES section is empty
         * or if it has only one value
         * then it does not make sense to
         * create drop-down menu
         * with just 0 or 1 values
         */
        if (!empty($a) && is_array($a) && count($a) > 1) {
            foreach ($a as $locale => $name) {
                $selected = ($locale === $this->locale) ? ' selected' : '';
                $ret .= sprintf($tpl, $locale, $selected, $name);
            }

            $tpl = '<select name="locale" class="locales" id="id_locale">%s</select>';

            $ret = \sprintf($tpl, $ret);
        }

        return \sprintf($tplWrapper, $ret);
    }


    /**
     * Get value of the language selection
     * drop-down menu options html
     *
     * This method should be called after setLocale()
     * so that current locale can be used
     * as the value of the 'selected' option
     * in the drop-down menu
     *
     * @return string html fragment
     */
    public function getOptions()
    {
        if (isset($_SESSION) && !empty($_SESSION['langs'])) {
            return $_SESSION['langs'];
        }

        $langs = $this->makeOptions();

        if (isset($_SESSION)) {
            $_SESSION['langs'] = $langs;
        }

        return $langs;
    }


    /**
     * Sets locale in Viewer object
     * and also in this object as well as
     * system-wide setlocale()
     *
     *
     * @param string $locale
     */
    public function set($locale)
    {
        d(' $locale: ' . $locale);
        $res = false;
        $locales = array(
            \str_replace('_', '-', $locale),
            \str_replace('-', '_', $locale),
            \strtolower(\substr($locale, 0, 2))
        );

        $this->locale = $locale;

        $this->Registry->Viewer->setLocale($locale);

        for ($i = 0; $i <= count($locales); $i += 1) {
            if (false !== $locale = @setlocale(LC_ALL, $locales[$i])) {
                d(' $locale: ' . $locale);
                break;
            }
        }

        return $res;
    }


    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * This method sets system-wide locale
     * It should be called after the Viewer object
     * has been instantiated
     * System locale affects how numbers
     * and time are formatted
     *
     */
    public function setLocale()
    {
        return @setlocale(LC_ALL, $this->locale, \str_replace('_', '-', $this->locale));
    }
}
