<?php
/**
 * Tagging plugin.
 *
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU GPL v2
 * @package Tagging
 */

/**
 * Class TagModule
 */
class TagModule extends Gdn_Module {

    protected $_TagData;

    protected $ParentID;

    protected $ParentType;

    protected $CategorySearch;

    /**
     * @param string $Sender
     */
    public function __construct($Sender = '') {
        $this->_TagData = false;
        $this->ParentID = null;
        $this->ParentType = 'Global';
        $this->CategorySearch = c('Plugins.Tagging.CategorySearch', false);
        parent::__construct($Sender);
    }

    /**
     *
     *
     * @param $Name
     * @param $Value
     */
    public function __set($Name, $Value) {
        if ($Name == 'Context') {
            $this->AutoContext($Value);
        }
    }

    /**
     *
     *
     * @param null $Hint
     */
    protected function AutoContext($Hint = null) {
        // If we're already configured, don't auto configure
        if (!is_null($this->ParentID) && is_null($Hint)) {
            return;
        }

        // If no hint was given, determine by environment
        if (is_null($Hint)) {
            if (Gdn::controller() instanceof Gdn_Controller) {
                $DiscussionID = Gdn::controller()->data('Discussion.DiscussionID', null);
                $CategoryID = Gdn::controller()->data('Category.CategoryID', null);

                if ($DiscussionID) {
                    $Hint = 'Discussion';
                } elseif ($CategoryID) {
                    $Hint = 'Category';
                } else {
                    $Hint = 'Global';
                }
            }
        }

        switch ($Hint) {
            case 'Discussion':
                $this->ParentType = 'Discussion';
                $DiscussionID = Gdn::controller()->data('Discussion.DiscussionID');
                $this->ParentID = $DiscussionID;
                break;

            case 'Category':
                if ($this->CategorySearch) {
                    $this->ParentType = 'Category';
                    $CategoryID = Gdn::controller()->data('Category.CategoryID');
                    $this->ParentID = $CategoryID;
                }
                break;
        }

        if (!$this->ParentID) {
            $this->ParentID = 0;
            $this->ParentType = 'Global';
        }

    }

    /**
     *
     *
     * @throws Exception
     */
    public function GetData() {
        $TagQuery = Gdn::sql();

        $this->AutoContext();

        $TagCacheKey = "TagModule-{$this->ParentType}-{$this->ParentID}";
        switch ($this->ParentType) {
            case 'Discussion':
                $Tags = TagModel::instance()->getDiscussionTags($this->ParentID, false);
                break;
            case 'Category':
                $TagQuery->join('TagDiscussion td', 't.TagID = td.TagID')
                    ->select('COUNT(DISTINCT td.TagID)', '', 'NumTags')
                    ->where('td.CategoryID', $this->ParentID)
                    ->groupBy('td.TagID')
                    ->Cache($TagCacheKey, 'get', array(Gdn_Cache::FEATURE_EXPIRY => 120));
                break;

            case 'Global':
                $TagCacheKey = 'TagModule-Global';
                $TagQuery->where('t.CountDiscussions >', 0, false)
                    ->Cache($TagCacheKey, 'get', array(Gdn_Cache::FEATURE_EXPIRY => 120));

                if ($this->CategorySearch) {
                    $TagQuery->where('t.CategoryID', '-1');
                }

                break;
        }

        if (isset($Tags)) {
            $this->_TagData = new Gdn_DataSet($Tags, DATASET_TYPE_ARRAY);
        } else {
            $this->_TagData = $TagQuery
                ->select('t.*')
                ->from('Tag t')
                ->orderBy('t.CountDiscussions', 'desc')
                ->limit(25)
                ->get();
        }

        $this->_TagData->DatasetType(DATASET_TYPE_ARRAY);
    }

    /**
     *
     *
     * @return string
     */
    public function AssetTarget() {
        return 'Panel';
    }

    /**
     *
     *
     * @return string
     */
    public function InlineDisplay() {
        if (!$this->_TagData) {
            $this->GetData();
        }

        if ($this->_TagData->numRows() == 0) {
            return '';
        }

        $String = '';
        ob_start();
        ?>
        <div class="InlineTags Meta">
            <?php echo T('Tagged'); ?>:
            <ul>
                <?php foreach ($this->_TagData->resultArray() as $Tag) :
?>
                    <?php if ($Tag['Name'] != '') :
?>
                        <li><?php
                            echo anchor(
                                htmlspecialchars(TagFullName($Tag)),
                                TagUrl($Tag, '', '/'),
                                array('class' => 'Tag_'.str_replace(' ', '_', $Tag['Name']))
                            );
                            ?></li>
                    <?php
endif; ?>
                <?php
endforeach; ?>
            </ul>
        </div>
        <?php
        $String = ob_get_contents();
        @ob_end_clean();
        return $String;
    }

    /**
     *
     *
     * @return string
     */
    public function ToString() {
        if (!$this->_TagData) {
            $this->GetData();
        }

        if ($this->_TagData->numRows() == 0) {
            return '';
        }

        $String = '';
        ob_start();
        ?>
        <div class="Box Tags">
            <?php echo panelHeading(T($this->ParentID > 0 ? 'Tagged' : 'Popular Tags')); ?>
            <ul class="TagCloud">
                <?php foreach ($this->_TagData->Result() as $Tag) :
?>
                    <?php if ($Tag['Name'] != '') :
?>
                        <li><?php
                            echo anchor(
                                TagFullName($Tag).' '.Wrap(number_format($Tag['CountDiscussions']), 'span', array('class' => 'Count')),
                                TagUrl($Tag, '', '/'),
                                array('class' => 'Tag_'.str_replace(' ', '_', $Tag['Name']))
                            );
                            ?></li>
                    <?php
endif; ?>
                <?php
endforeach; ?>
            </ul>
        </div>
        <?php
        $String = ob_get_contents();
        @ob_end_clean();
        return $String;
    }
}
