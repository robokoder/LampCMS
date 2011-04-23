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

use \Lampcms\WebPage;
use \Lampcms\Request;
use \Lampcms\Cookie;
use \Lampcms\Responder;
use \Lampcms\MongoDoc;
use \Lampcms\Twitter;

/**
 * Class for generating a popup page that starts the oauth dance
 * and this also serves as a callback url
 * to which twitter redirects after authorization
 *
 * Dependency is pecl OAuth extension!
 *
 * @author Dmitri Snytkine
 *
 */
class Logintwitter extends WebPage
{

	const REQUEST_TOKEN_URL = 'https://api.twitter.com/oauth/request_token';

	const ACCESS_TOKEN_URL = 'https://api.twitter.com/oauth/access_token';

	const AUTHORIZE_URL = 'https://api.twitter.com/oauth/authorize';

	protected $aAllowedVars = array('oauth_token');

	/**
	 * Array of data returned from Twitter
	 * This is the main user's profile and stuff
	 *
	 * @var array
	 */
	protected $aUserData;

	/**
	 * Object php OAuth
	 *
	 * @var object of type php OAuth
	 * must have oauth extension for this
	 */
	protected $oAuth;

	/**
	 * If user with the sid cookie already exists
	 * then we will create another record
	 * in the JOINT_ACCOUNT table
	 * this will be just for our own statistics and refs
	 * it holds records of account_a to account_b
	 * If during registration we detect by using sid cookie
	 * that user was already registered (but currently logged out)
	 * then we will create a record of this fact for our own references
	 * and then we will create new sid cookie and send it to user
	 * @var int
	 */
	protected $existingUidBySid;

	protected $bInitPageDoc = false;

	protected $aTW = array();


	/**
	 * Flag means new account will
	 * be created for this
	 * 'signed in with twitter' user
	 *
	 * @var bool
	 */
	protected $isNewAccount = false;


	/**
	 * Object of type UserTwitter
	 *
	 * We cannot just update the Viewer object because
	 * we need to create an object of type UserTwitter
	 * we will then replace the Viewer object with this new object
	 * via processLogin()
	 *
	 * @var object of type UserTwitter
	 */
	protected $oUser;

	/**
	 * Flag indicates that this is the
	 * request to connect Twitter account
	 * with existing user account.
	 *
	 * @var bool
	 */
	protected $bConnect = false;

	/**
	 * The main purpose of this class is to
	 * generate the oAuth token
	 * and then redirect browser to twitter url with
	 * this unique token
	 *
	 * No actual page generation will take place
	 *
	 * @see classes/WebPage#main()
	 */
	protected function main()
	{

		/**
		 * In case user is already logged in we will output
		 * the close window HTML and end the process
		 * This is to prevent crackers from continuesly using this
		 * page to bug down the server
		 */
		//if($this->isLoggedIn()){
		//	return $this->closeWindow();
		//}

		/**
		 * If user is logged in then this is
		 * a request to connect Twitter Account
		 * with existing account.
		 *
		 * @todo check that user does not already have
		 * Twitter credentials and if yes then call
		 * closeWindows as it would indicate that user
		 * is already connected with Twitter
		 */
		if($this->isLoggedIn()){
			$this->bConnect = true;
		}

		//$this->bConnect = $this->oRequest->get('cflag');
		d('$this->bConnect: '.$this->bConnect);

		$this->aTW = $this->oRegistry->Ini['TWITTER'];

		try {
			$this->oAuth = new \OAuth($this->aTW['TWITTER_OAUTH_KEY'], $this->aTW['TWITTER_OAUTH_SECRET'], OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
			$this->oAuth->enableDebug();  // This will generate debug output in your error_log
		} catch(\OAuthException $e) {
			e('OAuthException: '.$e->getMessage());

			throw new \Lampcms\Exception('Something went wrong during authorization. Please try again later'.$e->getMessage());
		}

		/**
		 * If this is start of dance then
		 * generate token, secret and store them
		 * in session and redirect to twitter authorization page
		 */
		if(empty($_SESSION['oauth']) || empty($this->oRequest['oauth_token'])){

			$this->startOauthDance();

		} else {
			$this->finishOauthDance();

		}

	}


	/**
	 * Generate oAuth request token
	 * and redirect to twitter for authentication
	 *
	 * @return object $this
	 *
	 * @throws Exception in case something goes wrong during
	 * this stage
	 */
	protected function startOauthDance()
	{
		try {
			// State 0 - Generate request token and redirect user to Twitter to authorize
			$_SESSION['oauth'] = $this->oAuth->getRequestToken(self::REQUEST_TOKEN_URL);

			d('$_SESSION[\'oauth\']: '.print_r($_SESSION['oauth'], 1));
			if(!empty($_SESSION['oauth'])){

				/**
				 * A more advanced way is to NOT use Location header
				 * but instead generate the HTML that contains the onBlur = focus()
				 * and then redirect with javascript
				 * This is to prevent from popup window going out of focus
				 * in case user clicks outsize the popup somehow
				 */
				$this->redirectToTwitter(self::AUTHORIZE_URL.'?oauth_token='.$_SESSION['oauth']['oauth_token']);
			} else {
				/**
				 * Here throw regular Exception, not Lampcms\Exception
				 * so that it will be caught ONLY by the index.php and formatted
				 * on a clean page, without any template
				 */

				throw new Exception("Failed fetching request token, response was: " . $this->oAuth->getLastResponse());
			}
		} catch(\OAuthException $e) {
			e('OAuthException: '.$e->getMessage().' '.print_r($e, 1));

			throw new \Exception('Something went wrong during authorization. Please try again later'.$e->getMessage());
		}


		return $this;
	}


	/**
	 * Step 2 in oAuth process
	 * this is when Twitter redirected the user back
	 * to our callback url, which calls this controller
	 * @return object $this
	 *
	 * @throws Exception in case something goes wrong with oAuth class
	 */
	protected function finishOauthDance(){

		try {
			/**
			 * This is a callback (redirected back from twitter page
			 * after user authorized us)
			 * In this case we must: create account or update account
			 * in USER table
			 * Re-create oViewer object
			 * send cookie to remember user
			 * and then send out HTML with js instruction to close the popup window
			 */
			d('Looks like we are at step 2 of authentication');

			// State 1 - Handle callback from Twitter and get and store an access token
			$this->oAuth->setToken($this->oRequest['oauth_token'], $_SESSION['oauth']['oauth_token_secret']);
			$aAccessToken = $this->oAuth->getAccessToken(self::ACCESS_TOKEN_URL);
			d('$aAccessToken: '.print_r($aAccessToken, 1));

			unset($_SESSION['oauth']);

			/**
			 * @todo
			 * there is a slight possibility that
			 * we don't get the oData back like if
			 * request for verify_credentials with token/secret fails
			 * This should not happend because user has just authorized us - this
			 * is a callback url after all.
			 * But still, what if... what if Twitter hickups and does not
			 * return valid response, then what should be do?
			 *
			 * Probably throw some generic exception telling user to try
			 * again in a few minutes
			 *
			 * So basically we should delegate this whole process to
			 * the Twitter->verifyCredentials()
			 *
			 */
			$this->oAuth->setToken($aAccessToken['oauth_token'], $aAccessToken['oauth_token_secret']);
			$this->oAuth->fetch('http://api.twitter.com/1/account/verify_credentials.json');

			$this->aUserData = json_decode($this->oAuth->getLastResponse(), 1);
			if(isset($this->aUserData['status'])){
				unset($this->aUserData['status']);
			}

			d('json: '.var_export($this->aUserData, true));

			$aDebug = $this->oAuth->getLastResponseInfo();
			d('debug: '.print_r($aDebug, 1));

			$this->aUserData = array_merge($this->aUserData, $aAccessToken);
			d('$this->aUserData '.print_r($this->aUserData, 1));

			$this->aUserData['_id'] = (!empty($this->aUserData['id_str'])) ? $this->aUserData['id_str'] : (string)$this->aUserData['id'];
			unset($this->aUserData['user_id']);

			$this->updateTwitterUserRecord();

			$this->createOrUpdate();
			if(!$this->bConnect){
				Cookie::sendLoginCookie($this->oRegistry->Viewer->getUid(), $this->oUser->rs);
			}

			$this->closeWindow();

		} catch(\OAuthException $e) {
			e('OAuthException: '.$e->getMessage().' '.print_r($e, 1));

			// throw new \Lampcms\Exception('Something went wrong during authorization. Please try again later'.$e->getMessage());
			/*
			 /**
			 * Cannot throw exception because then it would be
			 * displayed as regular page, with login block
			 * but the currently opened window is a popup window
			 * for showing twitter oauth page and we don't need
			 * a login form or any other elements of regular page there
			 */
			$err = 'Something went wrong during authorization. Please try again later'.$e->getMessage();
			exit(\Lampcms\Responder::makeErrorPage($err));
		}

		return $this;
	}


	/**
	 * Test to see if user with the twitter ID already exists
	 * by requesting tid_ key from cache
	 * this is faster than even a simple SELECT because
	 * the user object may already exist in cache
	 *
	 * If user not found, then create a record for
	 * a new user, otherwise update record
	 *
	 * @todo special case if this is 'connect' type of action
	 * where existing logged in user is adding twitter to his account
	 * then we should delegate to connect() method which
	 * does different things - adds twitter data to $this->oRegistry->Viewer
	 * but also first checks if another user already has
	 * this twitter account in which case must show error - cannot
	 * use same Twitter account by different users
	 *
	 * @return object $this
	 */
	protected function createOrUpdate(){
		$this->aUserData['utc_offset'] = (!empty($this->aUserData['utc_offset'])) ? $this->aUserData['utc_offset'] : Cookie::get('tzo', 0);

		$tid = $this->aUserData['_id']; // it will be string!
		d('$tid: '.$tid);
		$aUser = $this->getUserByTid($tid);

		if(!empty($this->bConnect)){
			d('this is connect action');

			$this->oUser = $this->oRegistry->Viewer;
			$this->connect($tid);
				
		} elseif(!empty($aUser)){
			$this->oUser = $oUser = \Lampcms\UserTwitter::factory($this->oRegistry, $aUser);
			$this->updateUser();
		} else {
			$this->isNewAccount = true;
			$this->createNewUser();
		}


		try{
			$this->processLogin($this->oUser);
		} catch(\Lampcms\LoginException $e){
			/**
			 * re-throw as regular exception
			 * so that it can be caught and show in popup window
			 */
			e('Unable to process login: '.$e->getMessage());
			throw new \Exception($e->getMessage());
		}

		d('SESSION oViewer: '.print_r($_SESSION['oViewer']->getArrayCopy(), 1).  'isNew: '.$this->oRegistry->Viewer->isNewUser());

		$this->updateLastLogin();

		//exit('processed');

		if($this->isNewAccount){
			$this->postTweetStatus();
		}

		return $this;
	}


	/**
	 * Add Twitter credentials to existing user
	 *
	 * @return $this
	 */
	protected function connect($tid){
		$aUser = $this->getUserByTid($tid);
		d('$aUser: '.print_r($aUser, 1));
		if(!empty($aUser) && ($aUser['_id'] != $this->oUser->getUid())){
			
			/**
			 * This error message will appear inside the 
			 * Small extra browser Window that Login with Twitter
			 * opens
			 * 
			 */
			$err = '<div class="larger"><p>This Twitter account is already connected to
			another registered user: <strong>' .$aUser['fn']. ' '.$aUser['ln']. '</strong><br>
			<br>
			A Twitter account cannot be associated with more than one account on this site<br>
			If you still want to connect Twitter account to this account you must use a different Twitter account</p>';
			$err .= '<br><br>
			<input type="button" class="btn-m" onClick="window.close();" value="&nbsp;OK&nbsp;">&nbsp;
			<input type="button"  class="btn-m" onClick="window.close();" value="&nbsp;Close&nbsp;">
			</div>';

			$s = Responder::makeErrorPage($err);
			echo ($s);
			exit;
		}

		$this->updateUser(false);
	}


	protected function createNewUser(){

		$aUser = array();

		$username = $this->makeUsername();
		$sid = Cookie::getSidCookie();
		d('sid is: '.$sid);

		$aUser['username'] = $username;
		$aUser['username_lc'] = \mb_strtolower($username, 'utf-8');
		$aUser['fn'] = $this->aUserData['name'];
		$aUser['avatar_external'] = $this->aUserData['profile_image_url'];

		$aUser['lang'] = $this->aUserData['lang'];
		$aUser['i_reg_ts'] = time();
		$aUser['date_reg'] = date('r');
		$aUser['role'] = 'external_auth';
		$aUser['tz'] = \Lampcms\TimeZone::getTZbyoffset($this->aUserData['utc_offset']);
		$aUser['rs'] =  (false !== $sid) ? $sid : \Lampcms\String::makeSid();
		$aUser['twtr_username'] = $this->aUserData['screen_name'];
		$aUser['oauth_token'] = $this->aUserData['oauth_token'];
		$aUser['oauth_token_secret'] = $this->aUserData['oauth_token_secret'];
		$aUser['twitter_uid'] = $this->aUserData['_id'];
		$aUser['i_rep'] = 1;

		$oGeoData = $this->oRegistry->Cache->{sprintf('geo_%s', Request::getIP())};
		$aProfile = array(
		'cc' => $oGeoData->countryCode,
		'country' => $oGeoData->countryName,
		'state' => $oGeoData->region,
		'city' => $oGeoData->city,
		'zip' => $oGeoData->postalCode);
		d('aProfile: '.print_r($aProfile, 1));

		$aUser = array_merge($aUser, $aProfile);

		if(!empty($this->aUserData['url'])){
			$aUser['url'] = $this->aUserData['url'];
		}

		if(!empty($this->aUserData['description'])){
			$aUser['description'] = $this->aUserData['description'];
		}

		d('aUser: '.print_r($aUser, 1));

		$this->oUser = \Lampcms\UserTwitter::factory($this->oRegistry, $aUser);

		/**
		 * This will mark this userobject is new user
		 * and will be persistent for the duration of this session ONLY
		 * This way we can know it's a newsly registered user
		 * and ask the user to provide email address but only
		 * during the same session
		 */
		$this->oUser->setNewUser();
		d('isNewUser: '.$this->oUser->isNewUser());
		$this->oUser->save();

		\Lampcms\PostRegistration::createReferrerRecord($this->oRegistry, $this->oUser);

		$this->oRegistry->Dispatcher->post($this->oUser, 'onNewUser');

		//exit(' new user: '.$this->oUser->isNewUser().' '.print_r($this->oUser->getArrayCopy(), 1));

		return $this;
	}


	/**
	 * The currect Viewer object may be updated with the data
	 * we got from Twitter api
	 *
	 * This means we found record for existing user by twitter uid
	 *
	 */
	protected function updateUser($bUpdateAvatar = true){
		d('adding Twitter credentials to User object');
		$this->oUser['oauth_token'] = $this->aUserData['oauth_token'];
		$this->oUser['oauth_token_secret'] = $this->aUserData['oauth_token_secret'];
		$this->oUser['twitter_uid'] = $this->aUserData['_id'];
		/**
		 * If NOT bUpdateAvatar then
		 * check avatar_external and only add one
		 * if one does not already exist
		 */
		if(!$bUpdateAvatar){
			$avatarTwitter = $this->oUser['avatar_external'];
			if(empty($avatarTwitter)){
				$this->oUser['avatar_external'] = $this->aUserData['profile_image_url'];
			}
		} else {
			$this->oUser['avatar_external'] = $this->aUserData['profile_image_url'];
		}

		$this->oUser->save();

		return $this;

	}


	/**
	 * Post tweet like
	 * "Joined this site"
	 * Also can and probably should add
	 * the person to follow
	 * our site's account
	 */
	protected function postTweetStatus(){

		//return $this;

		$sToFollow = $this->aTW['TWITTER_USERNAME'];
		$follow = (!empty($sToFollow)) ? ' #follow @'.$sToFollow : '';
		$siteName = $this->oRegistry->Ini->SITE_TITLE;
		$ourTwitterUsername = $this->oRegistry->Ini->SITE_URL.$follow;

		$oTwitter = new Twitter($this->oRegistry);

		if(!empty($ourTwitterUsername)){
			register_shutdown_function(function() use ($oTwitter, $siteName, $ourTwitterUsername){
				try{
					$oTwitter->followUser();

				} catch (\Lampcms\TwitterException $e){
					$message = 'Error in: '.$e->getFile(). ' line: '.$e->getLine().' message: '.$e->getMessage();
					//d($message);
				}

				/*try{
				 $oTwitter->postMessage('I Joined '.$siteName. ' '.$stuff);

				 } catch (\Lampcms\TwitterException $e){
				 $message = 'Exception in: '.$e->getFile(). ' line: '.$e->getLine().' message: '.$e->getMessage();

				 }*/
			});
		}
		
		return $this;
	}



	/**
	 * Create a new record in USERS_GFC table
	 * or update an existing record
	 *
	 * @param unknown_type $isUpdate
	 */
	protected function updateTwitterUserRecord(){

		$this->oRegistry->Mongo->USERS_TWITTER->save($this->aUserData);

		return $this;
	}


	/**
	 * Get user objecty ty twitter id (tid)
	 *
	 * @param string $tid Twitter id
	 *
	 * @return mixed array or null
	 *
	 */
	protected function getUserByTid($tid){

		$coll = $this->oRegistry->Mongo->USERS;
		$coll->ensureIndex(array('twitter_uid' => 1));

		$aUser = $coll->findOne(array('twitter_uid' => $this->aUserData['_id']));

		return $aUser;
	}


	/**
	 * Return html that contains JS window.close code and nothing else
	 *
	 * @return unknown_type
	 */
	protected function closeWindow(array $a = array()){
		d('cp a: '.print_r($a, 1));
		$js = '';
		/*if(!empty($a)){
			$o = json_encode($a);
			$js = 'window.opener.oSL.processLogin('.$o.')';
			}*/

		$tpl = '
		var myclose = function(){
		window.close();
		}
		if(window.opener){
		%s
		setTimeout(myclose, 500); // give opener window time to process login and cancell intervals
		}else{
			alert("not a popup window")
		}';
		d('cp');
		$script = sprintf($tpl, $js);

		$s = Responder::PAGE_OPEN. Responder::JS_OPEN.
		$script.
		Responder::JS_CLOSE.
		'<h2>You have successfully logged in. You should close this window now</h2>'.
		//print_r($_SESSION, 1).
		Responder::PAGE_CLOSE;
		d('cp s: '.$s);
		echo $s;
		fastcgi_finish_request();
		exit;
	}


	/**
	 * @todo add YUI Event lib
	 * and some JS to subscribe to blur event
	 * so that onBlur runs not just the first onBlur time
	 * but all the time
	 *
	 * @param string $url of Twitter oauth, including request token
	 * @return void
	 */
	protected function redirectToTwitter($url){
		/**
		 * @todo translate this string
		 *
		 */
		$s = Responder::PAGE_OPEN. Responder::JS_OPEN.
		'setTZOCookie = (function() {
		getTZO = function() {
		var tzo, nd = new Date();
		tzo = (0 - (nd.getTimezoneOffset() * 60));
		return tzo;
	    }
		var tzo = getTZO();
		document.cookie = "tzo="+tzo+";path=/";
		})();
		
		
		var myredirect = function(){
			window.location.assign("'.$url.'");
		};
			setTimeout(myredirect, 300);
			'.
		Responder::JS_CLOSE.
		'<div class="centered"><a href="'.$url.'">If you are not redirected in 2 seconds, click here to authenticate with Twitter</a></div>'.
		Responder::PAGE_CLOSE;

		exit($s);
	}


	/**
	 * Checks in username of twitter user
	 * already exists in our regular USERS table
	 * and if it does then prepends the @ to the username
	 * otherwise returns twitter username
	 *
	 * The result is that we will use the value of
	 * Twitter username as our username OR the @username
	 * if username is already taken
	 *
	 * @return string the value of username that will
	 * be used as our own username
	 *
	 */
	protected function makeUsername(){

		$res = $this->oRegistry->Mongo->USERS->findOne(array('twitter_uid' => $this->aUserData['_id']));

		$ret = (empty($res)) ? $this->aUserData['screen_name'] : '@'.$this->aUserData['screen_name'];
		d('ret: '.$ret);

		return $ret;

	}

}
