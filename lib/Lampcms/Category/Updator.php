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


namespace Lampcms\Category;

/**
 * Updates CATEGORIES collection
 * when new question or answer is added or removed
 * Updates count of questions, answers per category
 * as well as latest question array
 * is stored in a_latest tag
 *
 * @todo keep track of per category tags
 * CATEGORY_TAGS collection
 * increase/decrease count of tag
 * i_cat, tag, i_count
 * This collection will be updated only on questions add/remove
 * nothing to be done on answers.
 * Make use of generic _id
 *
 * @author Dmitri Snytkine
 *
 */
class Updator
{

    protected $Mongo;

    public function __construct(\Lampcms\Mongo\DB $Mongo)
    {
        $this->Mongo = $Mongo;
    }

    /**
     * Update the CATEGORY collection
     * with new question
     *
     * @param \Lampcms\Question $Q
     * @return \Lampcms\Category\Updator
     */
    public function addQuestion(\Lampcms\Question $Q)
    {

        $id = $Q->getCategoryId();
        if ($id > 0) {
            $this->ensureIndexes();
            $aLatest = array(
                'qid' => $Q->getResourceId(),
                'i_uid' => $Q->getOwnerId(),
                'url' => $Q->getSeoUrl(),
                'title' => $Q->getTitle(),
                'usr' => $Q['username'],
                'avtr' => $Q['avtr'],
                'ulink' => $Q['ulink']
            );

            $update = array(
                '$inc' => array('i_qcount' => 1),
                '$set' => array(
                    'i_qid' => $Q->getResourceId(),
                    'i_ts' => time(),
                    'hts' => date('F j, Y, g:i a T'),
                    'a_latest' => $aLatest
                )
            );

            $this->Mongo->CATEGORY->update(array('id' => $id), $update, array("upsert" => true));
        }

        return $this;
    }

    /**
     * When question is deleted
     * then decrease i_qcount but do it in such a way
     * that it cannot go below zero
     * Also if deleted question happends to be the one in the 'a_latest'
     * then also remove a_latest as well as set i_qid to 0
     *
     * @todo if a_latest is removed we really
     * need to replace it with another question
     * that is the "new latest" which would be one
     * posted before this one in the same category
     *
     * @param \Lampcms\Question $Q
     *
     * @return $this
     */
    public function removeQuestion(\Lampcms\Question $Q)
    {

        $id = $Q->getCategoryId();
        if ($id > 0) {
            $qid = $Q->getResourceId();
            $this->Mongo->CATEGORY->update(array('id' => $id, 'i_qcount' => array('$gt' => 0)), array('$inc' => array('i_qcount' => -1)));
            $this->Mongo->CATEGORY->update(array('id' => $id, 'i_qid' => $qid), array('$set' => array('i_qid' => 0, 'a_latest' => null)));
        }

        return $this;
    }

    /**
     * Decrease count of answers in
     * the category by 1 but only
     * of count is currently greater than 0
     * and only if Answer has non-zero category
     *
     * @param \Lampcms\Answer $A
     *
     * @return \Lampcms\Category\Updator
     */
    public function removeAnswer(\Lampcms\Answer $A)
    {
        $id = $A->getCategoryId();
        if ($id > 0) {
            $this->Mongo->CATEGORY->update(array('id' => $id, 'i_acount' => array('$gt' => 0)), array('$inc' => array('i_acount' => -1)));
        }

        return $this;
    }

    public function addAnswer(\Lampcms\Answer $A)
    {
        d('cp');
        $id = $A->getCategoryId();
        if ($id > 0) {
            $update = array('$inc' => array('i_acount' => 1),
                '$set' => array('i_ts' => time(), 'hts' => date('F j, Y, g:i a T')));

            $this->Mongo->CATEGORY->update(array('id' => $id), $update, array("upsert" => true));
        }

        return $this;
    }


    protected function ensureIndexes()
    {
        $this->Mongo->CATEGORY->ensureIndex(array('i_qid' => 1));
        $this->Mongo->CATEGORY->ensureIndex(array('id' => 1), array('unique' => true));

        return $this;
    }

}
