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


namespace Lampcms\Controllers;


use \Lampcms\String\HTMLStringParser;
use \Lampcms\Responder;
use \Lampcms\Request;
use \Lampcms\Utf8String;

/**
 * Controller for processing "Edit"
 * form for editing Question or Answer
 *
 * @todo should move the parsing to
 * new class so the whole parsing thing
 * can later be used from the API and not just
 * from this controller.
 *
 * @author Dmitri Snytkine
 *
 */
class Editor extends Edit
{

    protected $membersOnly = true;

    protected $requireToken = true;

    protected $bRequirePost = true;

    protected $aRequired = array('rid', 'rtype');

    /**
     * Object Utf8String represents body
     * of question
     *
     * @var object of type Utf8string
     */
    protected $Body;


    protected function main()
    {
        $this->getResource()
            ->checkPermission()
            ->makeForm();

        if ($this->Form->validate()) {
            $this->process()->updateQuestion()->returnResult();
        } else {
            $this->returnErrors();
        }
    }


    protected function returnErrors()
    {
        d('cp');

        if (Request::isAjax()) {
            d('cp');
            $aErrors = $this->Form->getErrors();

            Responder::sendJSON(array('formErrors' => $aErrors));
        }

        $this->makeTopTabs()
            ->makeMemo()
            ->setForm();
    }


    /**
     *
     * Process submitted form values
     *
     * @return \Lampcms\Controllers\Editor
     */
    protected function process()
    {
        $this->Registry->Dispatcher->post($this->Resource, 'onBeforeEdit');

        $formVals = $this->Form->getSubmittedValues();
        d('formVals: ' . print_r($formVals, 1));

        $this->Resource['b'] = $this->makeBody($formVals['qbody']);
        $this->Resource['i_words'] = $this->Body->asPlainText()->getWordsCount();

        /**
         * @important Don't attempt to edit the value of title
         * for the answer since it technically does not have the title
         * If we don't skip this step for Answer then title
         * of answer will be removed
         */
        if ($this->Resource instanceof \Lampcms\Question) {
            $oTitle = $this->makeTitle($formVals['title']);
            $title = $oTitle->valueOf();
            $this->Resource['title'] = $title;
            $this->Resource['url'] = $oTitle->toASCII()->makeLinkTitle()->valueOf();
            $this->Resource['a_title'] = \Lampcms\TitleTokenizer::factory($oTitle)->getArrayCopy();

            /**
             * @todo
             * Need to update 'title' of all answers to this question
             * But first check to see if title has actually changed
             */

        }

        $this->Resource->setEdited($this->Registry->Viewer, \strip_tags($formVals['reason']));
        $this->Resource->touch()->save();

        $this->Registry->Dispatcher->post($this->Resource, 'onEdit');

        return $this;
    }


    /**
     *
     * Update the contents of body
     * with edited content
     * If this is a question do extra steps;
     * unhighlight (just in case that actual highlighed words
     * have been edited), then re-apply highlightWords()
     * just in case some of the new word that belong to
     * tags have been added
     *
     * @param string $body
     *
     * @return string html of new body
     *
     */
    protected function makeBody($body)
    {
        /**
         * Must pass array('drop-proprietary-attributes' => false)
         * otherwise tidy removes rel="code"
         */
        $aEditorConfig = $this->Registry->Ini->getSection('EDITOR');
        $tidyConfig = ($aEditorConfig['ENABLE_CODE_EDITOR']) ? array('drop-proprietary-attributes' => false) : null;

        $this->Body = Utf8String::stringFactory($body)
            ->tidy($tidyConfig)
            ->safeHtml()
            ->asHtml();

        $Body = HTMLStringParser::stringFactory($this->Body)->parseCodeTags()->linkify()->reload()->setNofollow();

        if ($this->Resource instanceof \Lampcms\Question) {
            $Body->unhilight()->hilightWords($this->Resource['a_tags']);
        }

        $htmlBody = $Body->valueOf();

        d('after HTMLStringParser: ' . $htmlBody);

        return $htmlBody;
    }


    /**
     * Make new value of title
     *
     * @param string $title
     *
     * @return object of type Utf8String
     */
    protected function makeTitle($title)
    {
        $oTitle = Utf8String::stringFactory($title)->htmlentities()->trim();
        d('$oTitle ' . $oTitle);

        return $oTitle;
    }


    /**
     * If Edited resource was an ANSWER then we must update
     * last-modified of its QUESTION
     *
     * @return object $this
     */
    protected function updateQuestion()
    {
        if ('ANSWERS' === $this->collection) {
            d('need to update QUESTION');

            try {
                $this->Registry->Mongo->QUESTIONS->update(array('_id' => $this->Resource['i_qid']),
                    array(
                        '$set' => array(
                            'i_lm_ts' => time(),
                            'i_etag' => time())
                    )
                );
            } catch (\MongoException $e) {
                d('unable to update question ' . $e->getMessage());
            }
        }

        return $this;
    }


    protected function returnResult()
    {

        Responder::redirectToPage($this->Resource->getUrl());
    }
}
