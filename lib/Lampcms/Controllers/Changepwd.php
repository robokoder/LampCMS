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
 *    the website's Questions/Answers functionality is powered by lampcms.com
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

namespace Lampcms\Controllers;

use \Lampcms\String;

class Changepwd extends Resetpwd
{
    const TPL_SUCCESS = 'Password updated successfully';

    protected $membersOnly = true;

    protected $aRequired = array();

    protected $layoutID = 1;

    /**
     * @var object of type Form
     */
    protected $Form;


    /**
     * New password
     *
     * @var string
     */
    protected $newPwd;


    protected function main()
    {

        $this->Form = new \Lampcms\Forms\Changepwd($this->Registry);
		$this->Form->formTitle = $this->aPageVars['title'] = '@@Change Password@@';

		if ($this->Form->isSubmitted() && $this->Form->validate()) {
            $this->saveNewPassword();
            if (!empty($this->email)) {
                $this->emailPwd();
            }

            $this->aPageVars['body'] = '<div id="tools">@@Password updated successfully@@</div>';

        } else {
            $this->aPageVars['body'] = $this->Form->getForm();
        }
	}


    /**
     * Update ['pwd'] in Viewer object and save object
     *
     * @return object $this
     */
    protected function saveNewPassword()
    {
        $this->email = $this->Registry->Viewer['email'];
        $this->username = $this->Registry->Viewer['username'];
        $this->newPwd = $this->Request['pwd1'];

        $this->Registry->Viewer['pwd'] = String::hashPassword($this->newPwd);
        $this->Registry->Viewer->save();

        return $this;
    }
}
