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

use Lampcms\WebPage;
use Lampcms\Paginator;
use Lampcms\Template\Urhere;

/**
 * Generate page to view
 * questions that belong to one
 * particular category
 *
 * @author Dmitri Snytkine
 *
 */
class Viewcategory extends Unanswered
{
    /**
     * Indicates the current tab
     *
     * @var string
     */
    protected $qtab = 'categories';


    protected $slug;

    protected $aCategory;


    protected function main()
    {
        $this->slug = $this->Registry->Router->getSegment(1, 's');
        $this->pageID = (int)$this->Request->getPageID();
        $this->pagerPath = '{_viewcategory_}/' . $this->slug;
        $this->counterTaggedText = $this->_('Questions in this category');

        $this->getCategory()
            ->getCursor()
            ->paginate()
            ->sendCacheHeaders();

        $this->aPageVars['title'] = $this->_('Category') . ' :: ' . $this->aCategory['title'];
        $this->makeTopTabs()
            ->makeQlistHeader()
            ->makeCounterBlock()
            ->makeQlistBody();
        //->makeFollowedTags()
        //->makeRecentTags();

    }


    /**
     * Get category data by the value of 'slug'
     *
     * @throws \Lampcms\Lampcms404Exception if category does not exist
     * @return \Lampcms\Controllers\Viewcategory
     */
    protected function getCategory()
    {
        $this->aCategory = $this->Registry->Mongo->CATEGORY->findOne(array('slug' => $this->slug));
        if (empty($this->aCategory)) {
            throw new \Lampcms\Lampcms404Exception('Category ' . $this->slug . ' Not Found');
        }

        return $this;
    }

    protected function getCursor()
    {

        $sort = array('i_ts' => -1);

        $where = array('i_cat' => $this->aCategory['id']);
        /**
         * Exclude deleted items
         */
        $where['i_del_ts'] = null;

        $this->Cursor = $this->Registry->Mongo->QUESTIONS->find($where, $this->aFields);
        $this->count = $this->Cursor->count(true);
        $this->Cursor->sort($sort);

        return $this;
    }

    /**
     * (non-PHPdoc)
     * @see Lampcms\Controllers.Viewquestions::makeQlistHeader()
     * @return \Lampcms\Controllers\Viewcategory
     */
    protected function makeQlistHeader()
    {

        $Renderer = new \Lampcms\Category\Renderer($this->Registry);
        $breadCrumb = $Renderer->getBreadCrumb($this->aCategory['id'], false);
        $subs = '';
        $subCategories = $Renderer->getSubCategoriesOf($this->aCategory['id']);
        if (!empty($subCategories)) {
            $subs = \tplSubcategories::parse(array($this->_('Sub categories'), \tplSubcategory::loop($subCategories)), false);
        }

        $this->aPageVars['qheader'] = $breadCrumb . $subs;

        return $this;
    }

}
