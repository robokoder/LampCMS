<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
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
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website's Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
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
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms\Controllers;

use Lampcms\UserVotesBlock;
use Lampcms\WebPage;
use Lampcms\User;
use Lampcms\ProfileDiv;
use Lampcms\UserTagsBlock;
use Lampcms\UserQuestions;
use Lampcms\UserAnswers;

class Userinfo extends WebPage
{

	protected $layoutID = 1;

	protected $aRequired = array('uid');

	protected $aAllowedVars = array('username', 'mode', 'sort');

	protected $oUser;

	protected $vars = array(
	'profile' => '',
	'questions' => '',
	'answers' => '',
	'votes' => '',
	'tags' => '');

	protected function main(){
		$this->getUser()
		->checkUsername()
		->addProfile()
		->addQuestions()
		->addAnswers()
		->addVotes()
		->addTags();
	}


	protected function getUser(){
		$a = $this->oRegistry->Mongo->USERS->findOne(array('_id' => $this->oRequest['uid']));

		if(empty($a)){
			throw new \Lampcms\Exception('User not found');
		}

		$this->oUser = User::factory($this->oRegistry, $a);
		$this->aPageVars['title'] = $this->oUser->getDisplayName();

		return $this;
	}


	protected function checkUsername(){
		$supplied = $this->oRequest['username'];
		if(!empty($supplied)){
			$username = $this->oUser->username;
			if(!empty($username) && (strtolower($username) !== strtolower($supplied) )){
				d('supplied username '.$supplied.' is not the same as actual username: '.$username);

				throw new \Lampcms\RedirectException('/users/'.$this->oRequest['uid'].'/'.$username);
			}
		}

		return $this;
	}


	/**
	 * Add profile block
	 *
	 * @return object $this
	 */
	protected function addProfile(){

		$this->aPageVars['body'] = ProfileDiv::get($this->oRegistry, $this->oUser);

		return $this;
	}


	/**
	 * Add block with user questions
	 *
	 * @return object $this
	 *
	 * @todo finish up with pagination
	 *
	 */
	protected function addQuestions(){

		$this->aPageVars['body'] .= UserQuestions::get($this->oRegistry, $this->oUser);

		return $this;
	}


	/**
	 * Add block with user's answers
	 *
	 * @return object $this
	 *
	 * @todo finish up with pagination
	 *
	 */
	protected function addAnswers(){

		$this->aPageVars['body'] .= UserAnswers::get($this->oRegistry, $this->oUser);

		return $this;
	}


	/**
	 * Add block with user votes stats
	 *
	 * @return object $this
	 */
	protected function addVotes(){
		$this->aPageVars['body'] .= UserVotesBlock::get($this->oRegistry, $this->oUser);

		return $this;
	}


	/**
	 * Add block with user's tags
	 *
	 * @return object $this
	 */
	protected function addTags(){

		$this->aPageVars['body'] .= UserTagsBlock::get($this->oRegistry, $this->oUser);

		return $this;
	}

}
