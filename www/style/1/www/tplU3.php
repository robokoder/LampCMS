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

/**
 * Template for ONE User in "Members" page
 */
class tplU3 extends Lampcms\Template\Fast
{

    protected static function func(&$a)
    {
        if (!empty($a['avatar'])) {
            $a['avatar'] = '{_AVATAR_IMG_SITE_}{_DIR_}/w/img/avatar/sqr/' . $a['avatar'];
        } elseif (!empty($a['avatar_external'])) {
            $a['avatar'] = $a['avatar_external'];
        } elseif (!empty($a['gravatar']) && !empty($a['email'])) {
            $a['avatar'] = $a['gravatar']['url'] . hash('md5', $a['email']) . '?s=36' . '&d=' . $a['gravatar']['fallback'] . '&r=' . $a['gravatar']['rating'];
        } else {
            $a['avatar'] = '{_IMAGE_SITE_}{_DIR_}/images/avatar.png';
        }

        $fn = (!empty($a['fn'])) ? $a['fn'] : '';
        $ln = (!empty($a['ln'])) ? $a['ln'] : '';
        $mn = (!empty($a['mn'])) ? $a['mn'] : '';

        $displayName = $fn . ' ' . $mn . ' ' . $ln;

        $a['displayName'] = (strlen($displayName) > 2) ? $displayName : ((array_key_exists('username', $a)) ? $a['username'] : '');

        $lastActive = (!empty($a['i_lm_ts'])) ? $a['i_lm_ts'] : $a['i_reg_ts'];
        $registered = $a['i_reg_ts'];
        $a['since'] = \Lampcms\TimeAgo::format(new \DateTime(date('r', $registered)));
        $a['last_seen'] = \Lampcms\TimeAgo::format(new \DateTime(date('r', $lastActive)));

        if ('deleted' == $a['role']) {
            $a['deleted'] = 'deleted';
        }
    }


    protected static $vars = array(
        '_id' => '', //1
        'displayName' => '', //2
        'i_rep' => '', //3
        'avatar' => '', //4
        'username' => '', //5
        'since' => '', //6
        'registered_l' => '@@Registered@@', //7
        'last_seen_l' => '@@Last seen@@', //8
        'last_seen' => '', //9
        'reputation_l' => '@@Current User Reputation score@@', //10
        'deleted' => '' //11
    );


    protected static $tpl = '
	<div class="u3 %11$s" id="uid-%1$s">
		<div title="%10$s %3$s" class="fl cb rounded3 i_rep ttt">%3$s</div>
		<div class="avtr_bg fl mt-12 cb imgloader" style=\'background-image:url("%4$s");\'>&nbsp;</div>
		<div class="u4 mt-12">
			<a href="{_WEB_ROOT_}/{_userinfo_}/%1$s/%5$s">%2$s</a><br>
		    <span class="reg_time">%7$s<br>%6$s</span><br>
		    <span class="seen">%8$s<br>%9$s</span>
		</div>
	</div>';

}
