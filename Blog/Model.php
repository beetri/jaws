<?php
/**
 * Blog Gadget
 *
 * @category   GadgetModel
 * @package    Blog
 * @author     Jonathan Hernandez <ion@suavizado.com>
 * @author     Pablo Fischer <pablo@pablo.com.mx>
 * @author     Ali Fazelzadeh <afz@php.net>
 * @copyright  2004-2013 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/gpl.html
 */
class Blog_Model extends Jaws_Gadget_Model
{
    /**
     * Has the Atom pointer to create the RSS/XML files
     *
     * @var     object  $_Atom  AtomFeed object
     * @access  private
     */
    var $_Atom = null;

    /**
     * Holds the tree category stuff
     *
     * @var     array   $_Tree
     * @access  private
     */
    var $_Tree = array(
                       'children'  => array(),
                       'data'      => array(),
                       'structure' => array(),
                       );

    /**
     * Get entries as a calendar
     *
     * @access  public
     * @param   string  $begintime  Begin date time
     * @param   string  $endtime    End date time
     * @return  mixed   An array of entries of a certain year and month and Jaws_Error on error
     */
    function GetEntriesAsCalendar($begintime, $endtime)
    {
        $params = array();
        $params['begintime'] = $begintime;
        $params['endtime']   = $endtime;
        $params['now']       = $GLOBALS['db']->Date();
        $params['published'] = true;

        $sql = "
            SELECT
                [title], [fast_url], [publishtime]
            FROM [[blog]]
            WHERE
                [published] = {published}
              AND
                [publishtime] >= {begintime}
              AND
                [publishtime] < {endtime}
              AND
                [publishtime] <= {now}
            ORDER BY [publishtime] asc";

        $types = array('text', 'text', 'text');
        $result = $GLOBALS['db']->queryAll($sql, $params, $types);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_ENTRIES_ASCALENDAR'), _t('BLOG_NAME'));
        }

        return $result;
    }

    /**
     * Get entries that match parameters
     *
     * @access  public
     * @param   int     $limit    Limit of data
     * @param   string  $filter   First filter it can be: NOTHING, RECENT or MM:YYYY</param>
     * @param   string  $category Category id
     * @param   string  $status   Status of the entry, 0 = Draft, 1 = Published
     * @param   string  $match    Match word
     * @param   string  $user_id  User id
     * @return  mixed   An array of entries and Jaws_Error on error
     */
    function AdvancedSearch($limit, $filter, $category, $status, $match, $user_id)
    {
        // Removed until ACLs are in place.
        /*$sql = 'SELECT [[blog]].[id], [user_id], [username], [nickname],
                [category_id], [title], [publishtime], [published]
            FROM [[blog]] INNER JOIN [[users]]
            ON [[blog]].[user_id] = [[users]].[id] ';*/

        $params = array();

        if (!is_bool($status)) {
            if (is_numeric($status)) {
                $params['status'] = $status == 1 ? true : false;
            } elseif (is_string($status)) {
                $params['status'] = $status == 'Y' ? true : false;
            }
        } else {
            $params['status'] = $status;
        }

        $params['filter']   = $filter;
        $params['category'] = $category;
        $params['match']    = $match;
        $params['user_id']  = $user_id;

        $sql = '
            SELECT
                [[blog]].[id],
                [[blog]].[user_id],
                [username],
                [title],
                [summary],
                [text],
                [fast_url],
                [[blog]].[publishtime],
                [[blog]].[updatetime],
                [published]
            FROM [[blog]]
            INNER JOIN [[users]] ON [[blog]].[user_id] = [[users]].[id]';
            if (trim($params['category']) != '') {
                $sql .='
                INNER JOIN [[blog_entrycat]] ON [[blog]].[id] = [[blog_entrycat]].[entry_id]';
            }
        if (trim($params['match']) != '') {
            $searchdata = explode(' ', $params['match']);
            /**
             * This query needs more work, not use $v straight, should be
             * like rest of the param stuff.
             */
            $i = 0;
            foreach ($searchdata as $v) {
                $v = trim($v);
                $sql .=(stristr($sql, 'WHERE')) ? ' AND ' : ' WHERE ';
                $sql .= "([[blog]].[title] LIKE {textLike_".$i."} OR [summary] LIKE {textLike_".$i."} OR [text] LIKE {textLike_".$i."})";
                $params['textLike_'.$i] = '%'.$v.'%';
                $i++;
            }

        }

        if (trim($status) != '') {
            $sql .=(stristr($sql, 'WHERE')) ? ' AND ' : ' WHERE ';
            $sql .= ' [published] = {status}';
        }

        if (trim($params['category']) != '') {
            $sql .=(stristr($sql, 'WHERE')) ? ' AND ' : ' WHERE ';
            $sql .= ' [[blog_entrycat]].[category_id] = {category}';
        }

        if (!in_array($params['filter'], array('NOTHING', 'RECENT'))) {
            $date = explode(':', $params['filter']);
            $params['year']  = $date[1];
            $params['month'] = $date[0];
            $GLOBALS['db']->dbc->loadModule('Function', null, true);
            $year  = $GLOBALS['db']->dbc->function->substring('[[blog]].[publishtime]', 1, 4);
            $month = $GLOBALS['db']->dbc->function->substring('[[blog]].[publishtime]', 6, 2);

            $sql .= (stristr($sql, 'WHERE')) ? ' AND ':' WHERE ';
            $sql .= " $year = {year}
                    AND $month = {month}";
        }

        if (is_numeric($limit)) {
            $result = $GLOBALS['db']->setLimit(10, $limit);
            if (Jaws_Error::IsError($result)) {
                return new Jaws_Error(_t('BLOG_ERROR_ADVANCED_SEARCH'), _t('BLOG_NAME'));
            }
        }

        if (!$this->gadget->GetPermission('ModifyOthersEntries')) {
            if (trim($params['user_id']) != '') {
                $sql .=(stristr($sql, 'WHERE')) ? ' AND ' : ' WHERE ';
                $sql .= " [user_id]= {user_id}";
            }
        }
        $sql .= ' ORDER BY [[blog]].[publishtime] desc';


        $types = array('integer', 'integer', 'text', 'text', 'text',
                       'text', 'text', 'timestamp', 'timestamp', 'boolean');
        $result = $GLOBALS['db']->queryAll($sql, $params, $types);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('BLOG_ERROR_ADVANCED_SEARCH'), _t('BLOG_NAME'));
        }

        return $result;
    }

    /**
     * Get date limitation of the blog entries
     *
     * @access  public
     * @param   bool    $published      is published
     * @return  array   An array that has the date limitation of blog entries
     */
    function GetPostsDateLimitation($published = null)
    {
        $params = array();

        $sql = '
            SELECT
                MIN([publishtime]) as min_date,
                MAX([publishtime]) as max_date,
                COUNT([id]) as qty_posts
            FROM [[blog]]';

        if (!is_null($published)) {
            $params['published'] = $published;
            $sql .= ' WHERE [published] = {published}';
        }

        $summary = $GLOBALS['db']->queryRow($sql, $params);
        if (Jaws_Error::IsError($summary)) {
            $summary = array();
        }

        return $summary;
    }

    /**
     * Get summary of the blog
     *
     * @access  public
     * @return  array   An array that has the summary of blog entries
     */
    function GetSummary()
    {
        $summary = $this->GetPostsDateLimitation();

        // Avg. entries per week
        if (isset($summary['min_date'])) {
            $dfirst    = strtotime($summary['min_date']);
            $dlast     = strtotime($summary['max_date']);
            $weekfirst = date('W', $dfirst);
            $yearfirst = date('Y', $dfirst);
            $weeklast  = date('W', $dlast);
            $yearlast  = date('Y', $dlast);
            if ($yearlast > $yearfirst) {
                // Ok ok, we assume 53 weeks per year...
                $nweeks =(54 - $weekfirst) +(53 *(($yearlast - 1) - $yearfirst)) + $weeklast;
            } else {
                $nweeks = $weeklast - $weekfirst;
            }

            if ($nweeks != 0) {
                $avg = round($summary['qty_posts'] / $nweeks);
            } else {
                $avg = $summary['qty_posts'];
            }

            $summary['AvgEntriesPerWeek'] = $avg;
        } else {
            $summary['min_date'] = null;
            $summary['max_date'] = null;
            $summary['AvgEntriesPerWeek'] = null;
        }

        // Recent entries
        $sql = '
            SELECT
                [id], [title], [fast_url], [published], [publishtime]
            FROM [[blog]]
            ORDER BY [publishtime] desc';

        $result = $GLOBALS['db']->setLimit('10');
        if (Jaws_Error::IsError($result)) {
            return $summary;
        }

        $types = array('integer', 'text', 'text', 'boolean', 'timestamp');
        $result = $GLOBALS['db']->queryAll($sql, null, $types);
        if (!Jaws_Error::IsError($result) && $result) {
            foreach ($result as $r) {
                $summary['Entries'][] = $r;
            }
        }

        if (Jaws_Gadget::IsGadgetInstalled('Comments')) {
            $cModel = $GLOBALS['app']->LoadGadget('Comments', 'Model', 'Comments');
            // total comments
            $summary['CommentsQty'] = $cModel->GetCommentsCount($this->gadget->name);
            // recent comments
            $comments = $cModel->GetComments($this->gadget->name, 10);
            if (Jaws_Error::IsError($comments)) {
                return $comments;
            }

            foreach ($comments as $r) {
                $summary['Comments'][] = array(
                    'id'         => $r['id'],
                    'name'       => $r['name'],
                    'createtime' => $r['createtime']
                );
            }
        }

        return $summary;
    }


    /**
     * Get categories
     *
     * @access  public
     * @return  mixed   A list of categories and Jaws_Error on error
     */
    function GetCategories()
    {
        $catTable = Jaws_ORM::getInstance()->table('blog_category');
        $catTable->select('id', 'name', 'fast_url', 'description', 'createtime', 'updatetime');
        $catTable->orderBy('name');
        return $catTable->fetchAll();
    }

    /**
     * Gets a category data
     *
     * @access  public
     * @param   int     $id  Category ID
     * @return  mixed   Array of category data or Jaws_Error
     */
    function GetCategory($id)
    {
        $catTable = Jaws_ORM::getInstance()->table('blog_category');
        $catTable->select(
            'id', 'name', 'fast_url', 'description',
            'meta_keywords', 'meta_description', 'createtime', 'updatetime'
        );

        if (is_numeric($id)) {
            $catTable->where('id', $id);
        } else {
            $catTable->where('fast_url', $id);
        }

        return $catTable->fetchRow();
    }

    /**
     * Get a category
     *
     * @access  public
     * @param   string  $name   category name
     * @return  mixed   A category array or Jaws_Error
     */
    function GetCategoryByName($name)
    {
        $params = array();
        $GLOBALS['db']->dbc->loadModule('Function', null, true);
        $params['name'] = $GLOBALS['app']->UTF8->strtolower($name);
        $lowerName = $GLOBALS['db']->dbc->function->lower('[name]');

        $sql = "
            SELECT
                [id], [name], [description], [fast_url], [createtime], [updatetime]
            FROM [[blog_category]] 
            WHERE
                $lowerName = {name}";

        $result = $GLOBALS['db']->queryRow($sql, $params);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_CATEGORY'), _t('BLOG_NAME'));
        }

        return $result;
    }

    /**
     * Get categories in a given entry
     *
     * @access  public
     * @param   int     $post_id  Post ID
     * @return  array   Returns an array with the categories in a given post
     */
    function GetCategoriesInEntry($post_id)
    {
        $params = array();
        $params['id'] = $post_id;
        $sql = '
            SELECT [category_id] as id, [name], [fast_url]
            FROM [[blog_entrycat]]
            INNER JOIN [[blog_category]] ON [category_id] = [id]
            WHERE [entry_id] = {id}';
        $categories = $GLOBALS['db']->queryAll($sql, $params);
        if (Jaws_Error::isError($categories)) {
            return array();
        }

        return $categories;
    }

    /**
     * Get categories in entries
     *
     * @access  public
     * @param   int     $ids Array with post id's
     * @return  array   Returns an array with the categories in a given post
     */
    function GetCategoriesInEntries($ids)
    {
        $categories = array();
        if (is_array($ids) && count($ids) > 0) {
            $in = implode(',', $ids);

            $sql = "
                SELECT
                    [category_id] as id, [entry_id], [name], [fast_url]
                FROM [[blog_entrycat]]
                INNER JOIN [[blog_category]] ON [category_id] = [id]
                WHERE [entry_id] IN ({$in})";
            $categories = $GLOBALS['db']->queryAll($sql);
            if (Jaws_Error::isError($categories)) {
                return array();
            }
        }

        return $categories;
    }

    /**
     * Get entries
     *
     * @access  public
     * @param   int     $cat            Category ID
     * @param   array   $conditions     Array with extra conditions
     * @param   array   $extraparams    Array with extra params
     * @param   int     $extralimit     Integer which limits number of results
     * @param   int     $extraoffset    Integer which sets an offset to skip results
     * @return  array   Returns an array of entries and Jaws_Error on error
     */
    function GetEntries($cat = null, $conditions = null, $extraparams = null, $extralimit = null, $extraoffset = null)
    {
        $params = array();
        $params['category']  = (int)$cat;
        $params['published'] = true;
        $params['now']       = $GLOBALS['db']->Date();
        $sql = '
            SELECT
                [[blog]].[id],
                [username],
                [email],
                [nickname],
                [[blog]].[title],
                [[blog]].[fast_url],
                [summary],
                [text],
                [[blog]].[publishtime],
                [[blog]].[updatetime],
                [comments],
                [clicks],
                [allow_comments]
            FROM [[blog]]
            LEFT JOIN [[users]] ON [[blog]].[user_id] = [[users]].[id]';
        if (!empty($cat)) {
            $sql .= '
                INNER JOIN [[blog_entrycat]] ON [[blog]].[id] = [[blog_entrycat]].[entry_id]
                WHERE [[blog_entrycat]].[category_id] = {category} AND ';
        } else {
            $sql .= '
                WHERE ';
        }

        $sql .= '[published] = {published} AND [[blog]].[publishtime] <= {now}';
        if (is_null($conditions)) {
            $sql .= '
                ORDER BY [[blog]].[publishtime] desc ';

            if (is_null($extralimit)) {
                    $extralimit =  $this->gadget->registry->fetch('last_entries_limit');
            }
            $result = $GLOBALS['db']->setLimit($extralimit, $extraoffset);
            if (Jaws_Error::IsError($result)) {
                return new Jaws_Error(_t('BLOG_ERROR_GETTING_ENTRIES'), _t('BLOG_NAME'));
            }
        } else {
            // Add conditions to query
            if (is_array($extraparams)) {
                $params = array_merge($params, $extraparams);
            }

            $sql .= ' '.$conditions.' ';
            $sql .= " ORDER BY [[blog]].[publishtime] desc ";

            if (is_null($extralimit)) {
                    $extralimit =  $this->gadget->registry->fetch('last_entries_limit');
            }
            $result = $GLOBALS['db']->setLimit($extralimit, $extraoffset);
            if (Jaws_Error::IsError($result)) {
                return new Jaws_Error(_t('BLOG_ERROR_GETTING_ENTRIES'), _t('BLOG_NAME'));
            }
        }

        $types = array('integer', 'text', 'text', 'text',
                       'text', 'text', 'text', 'text', 'timestamp', 'timestamp',
                       'integer', 'integer', 'boolean');
        $result = $GLOBALS['db']->queryAll($sql, $params, $types);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_ENTRIES'), _t('BLOG_NAME'));
        }

        $entries = array();
        $ids     = array();
        //Only load Jaws_Gravatar if we really have entries
        if (count($result) > 0) {
            $date = $GLOBALS['app']->loadDate();
            require_once JAWS_PATH . 'include/Jaws/Gravatar.php';
        }
        foreach ($result as $r) {
            $r['avatar_source'] = Jaws_Gravatar::GetGravatar($r['email']);
            $r['categories']    = array();
            $entries[$r['id']]  = $r;
            $ids[] = $r['id'];
        }
        $result = null;
        $categories = $this->GetCategoriesInEntries($ids);
        foreach ($categories as $cat) {
            $entries[$cat['entry_id']]['categories'][] = array('id'       => $cat['id'],
                                                               'name'     => $cat['name'],
                                                               'fast_url' => $cat['fast_url']);
        }
        $categories = null;

        return $entries;
    }

    /**
     * Get entries in a given page ordered by date (page size = last_entries_limit)
     *
     * @access  public
     * @param   int     $page
     * @param   string  $min_date   minimum date
     * @param   string  $max_date   maximum date
     * @return  array   Returns an array of entries of a certain date and Jaws_Error on error
     */
    function GetEntriesByDate($page, $min_date, $max_date)
    {
        if ($page > 0) {
            $page = $page - 1;
        } else {
            $page = 0;
        }

        $limit = $this->gadget->registry->fetch('last_entries_limit');
        $offset = $limit * $page;

        $params = array();
        $params['min_date'] = $min_date;
        $params['max_date'] = $max_date;
        $params['published'] = true;

        $sql = " AND [published] = {published}
                 AND [[blog]].[publishtime] >= {min_date} AND [[blog]].[publishtime] < {max_date}";

        return $this->GetEntries(null, $sql, $params, $limit, $offset);
    }

    /**
     * Get a list of comments
     *
     * @access  public
     * @param   int     $id         ID of the comment
     * @return  mixed   Returns a list of comments and Jaws_Error on error
     */
    function GetComments($id)
    {
        $cModel = $GLOBALS['app']->LoadGadget('Comments', 'Model', 'Comments');
        $comments = $cModel->GetComments($this->gadget->name, 0, $id, 'entry', array(1), true);
        if (Jaws_Error::IsError($comments)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_COMMENTS'), _t('BLOG_NAME'));
        }

        $this->_AdditionalCommentsData($comments);
        return $comments;
    }

    /**
     * Puts avatar and format time for given comments
     * 
     * @access  private
     * @param   array   $comments   reference to comments array
     * @param   string  $prenum     
     */
    function _AdditionalCommentsData(&$comments, $prenum = '')
    {
        require_once JAWS_PATH.'include/Jaws/Gravatar.php';
        $num = 0;
        foreach ($comments as $k => $v) {
            $num++;
            $comments[$k]['avatar_source'] = Jaws_Gravatar::GetGravatar($v['email']);
            $comments[$k]['createtime']    = $v['createtime'];
            $comments[$k]['num'] = $prenum.$num;
        }
    }

    /**
     * Get a list of comments
     *
     * @access  public
     * @param   string  $filterby Filter to use(postid, author, email, url, title, comment)
     * @param   string  $filter   Filter data
     * @param   string  $status   Spam status (approved, waiting, spam)
     * @param   mixed   $limit    Data limit (numeric/boolean)
     * @return  mixed   Returns a list of comments and Jaws_Error on error
     */
    function GetCommentsFiltered($filterby, $filter, $status, $limit)
    {
        $cModel = $GLOBALS['app']->LoadGadget('Comments', 'Model', 'Comments');
        $filterMode = '';
        switch($filterby) {
        case 'postid':
            $filterMode = COMMENT_FILTERBY_REFERENCE;
            break;
        case 'name':
            $filterMode = COMMENT_FILTERBY_NAME;
            break;
        case 'email':
            $filterMode = COMMENT_FILTERBY_EMAIL;
            break;
        case 'url':
            $filterMode = COMMENT_FILTERBY_URL;
            break;
        case 'title':
            $filterMode = COMMENT_FILTERBY_TITLE;
            break;
        case 'ip':
            $filterMode = COMMENT_FILTERBY_IP;
            break;
        case 'comment':
            $filterMode = COMMENT_FILTERBY_MESSAGE;
            break;
        case 'various':
            $filterMode = COMMENT_FILTERBY_VARIOUS;
            break;
        case 'status':
            $filterMode = COMMENT_FILTERBY_STATUS;
            break;
        default:
            $filterMode = null;
            break;
        }

        $comments = $cModel->GetFilteredComments($this->gadget->name, $filterMode, $filter, $status, $limit);
        if (Jaws_Error::IsError($comments)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_FILTERED_COMMENTS'), _t('BLOG_NAME'));
        }

        $commentsGravatar = array();
        require_once JAWS_PATH.'include/Jaws/Gravatar.php';
        foreach ($comments as $r) {
            $r['avatar_source'] = Jaws_Gravatar::GetGravatar($r['email']);
            $r['createtime']    = $r['createtime'];
            $commentsGravatar[] = $r;
        }

        return $commentsGravatar;
    }

    /**
     * Get a comment
     *
     * @access  public
     * @param   int     $id     ID of the comment
     * @return  mixed   Properties of a comment and Jaws_Error on error
     */
    function GetComment($id)
    {
        $cModel = $GLOBALS['app']->LoadGadget('Comments', 'Model', 'Comments');
        $comment = $cModel->GetComment($id, $this->gadget->name);
        if (Jaws_Error::IsError($comment)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_COMMENT'), _t('BLOG_NAME'));
        }

        require_once JAWS_PATH . 'include/Jaws/Gravatar.php';
        if ($comment) {
            $comment['avatar_source'] = Jaws_Gravatar::GetGravatar($comment['email']);
            $comment['createtime']    = $comment['createtime'];
            $comment['comments']      = $comment['msg_txt'];
        }

        return $comment;
    }

    /**
     * This function mails the comments to the admin and
     * to the user when he asks for it.
     *
     * @access  public
     * @param   int     $id            The blog id.
     * @param   string  $title      The email title
     * @param   string  $from_email The email to sendto
     * @param   string  $comment    The body of the email (The actual comment)
     * @param   string  $url        The url of the blog id.
     */
    function MailComment($id, $title, $from_email, $comment, $url)
    {
        $sql = '
            SELECT [[users]].[email]
            FROM [[users]]
            INNER JOIN [[blog]] ON [[users]].[id] = [[blog]].[user_id]
            WHERE [[blog]].[id] = {id}';

        $author_email = $GLOBALS['db']->queryOne($sql, array('id' => $id));
        if (Jaws_Error::IsError($author_email)) {
            $author_email = '';
        }

        $site_url   = $GLOBALS['app']->getSiteURL('/');
        $site_name  = $this->gadget->registry->fetch('site_name', 'Settings');

        $tpl = $this->gadget->loadTemplate('SendComment.html');
        $tpl->SetBlock('comment');
        $tpl->SetVariable('comment',   $comment);
        $tpl->SetVariable('lbl-url',   _t("BLOG_COMMENT_MAIL_VISIT"));
        $entry_url =& Piwi::CreateWidget('Link',
                                    $title,
                                    $GLOBALS['app']->Map->GetURLFor('Blog',
                                                                    'SingleView',
                                                                    array('id' => $id), true));
        $tpl->SetVariable('url',       $entry_url->Get());
        $tpl->SetVariable('site-name', $site_name);
        $tpl->SetVariable('site-url',  $site_url);
        $tpl->ParseBlock('comment');
        $template = $tpl->Get();

        require_once JAWS_PATH . '/include/Jaws/Mail.php';
        $mail = new Jaws_Mail;
        $subject = _t('BLOG_COMMENT_REPLY', $id). ' - ' . $title;
        $mail->SetFrom($from_email);
        $mail->AddRecipient($author_email);
        $mail->AddRecipient('', 'cc');
        $mail->SetSubject($subject);
        $mail->SetBody($template, 'html');
        $result = $mail->send();
    }

    /**
     * Get entries as an archive
     *
     * @access  public
     * @return  mixed   Returns a list of entries in Archive Format and Jaws_Error on error
     */
    function GetEntriesAsArchive()
    {
        $params              = array();
        $params['published'] = true;
        $params['now']       = $GLOBALS['db']->Date();

        $sql = '
            SELECT
                [id],
                [publishtime],
                [updatetime],
                [title],
                [fast_url],
                [comments]
            FROM [[blog]]
            WHERE [published] = {published} AND [publishtime] <= {now}
            ORDER BY [publishtime] desc';

        $result = $GLOBALS['db']->queryAll($sql, $params);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_ENTRIES_ASARCHIVE'), _t('BLOG_NAME'));
        }

        return $result;
    }


    /**
     * Get entries as a history
     *
     * @access  public
     * @return  mixed   Returns a list of entries in History Format and Jaws_Error on error
     */
    function GetEntriesAsHistory()
    {
        $params              = array();
        $params['published'] = true;
        $params['now']       = $GLOBALS['db']->Date();

        $sql = '
            SELECT
                [publishtime]
            FROM [[blog]]
            WHERE [published] = {published} AND [publishtime] <= {now}
            ORDER BY [publishtime] desc';

        $result = $GLOBALS['db']->queryAll($sql, $params);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_ENTRIES_ASHISTORY'), _t('BLOG_NAME'));
        }

        return $result;
    }

    /**
     * Get entries grouped by categories
     *
     * @access  public
     * @return  mixed   Returns a list of entries in Category Format and Jaws_Error on error
     */
    function GetEntriesAsCategories()
    {
        $params              = array();
        $params['published'] = true;
        $params['now']       = $GLOBALS['db']->Date();

        $sql = '
            SELECT
                [[blog_category]].[id], [name], [[blog_category]].[fast_url],
                COUNT([[blog_entrycat]].[entry_id]) as howmany
            FROM [[blog_category]]
            INNER JOIN [[blog_entrycat]] ON [[blog_category]].[id] = [[blog_entrycat]].[category_id]
            INNER JOIN [[blog]] ON [[blog]].[id] = [entry_id]
            WHERE [published] = {published} AND [[blog]].[publishtime] <= {now}
            GROUP BY
                [[blog_category]].[id], [name], [[blog_category]].[fast_url]
            ORDER BY [name]';

        $result = $GLOBALS['db']->queryAll($sql, $params);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_ENTRIES_ASCATEGORIES'), _t('BLOG_NAME'));
        }

        return $result;
    }

    /**
     * Get last entries of all categories or just of only one category
     *
     * @access  public
     * @param   int     $cat    Category ID
     * @return  mixed   Returns a list of recent entries and Jaws_Error on error
     */
    function GetRecentEntries($cat = null, $limit = 0)
    {
        $params              = array();
        $params['published'] = true;
        $params['now']       = $GLOBALS['db']->Date();
        
        $sql = '
            SELECT
                [[blog]].[id],
                [user_id],
                [username],
                [[users]].[nickname],
                [title],
                [summary],
                [text],
                [fast_url],
                [[blog]].[publishtime],
                [[blog]].[updatetime],
                [comments],
                [clicks],
                [allow_comments],
                [published]
            FROM [[blog]]
            LEFT JOIN [[users]] ON [[blog]].[user_id] = [[users]].[id]';
        if (is_numeric($cat)) {
            $params['cat'] = $cat;
            $sql.= '  INNER JOIN [[blog_entrycat]] ON [[blog]].[id] = [[blog_entrycat]].[entry_id] 
                      WHERE [[blog_entrycat]].[category_id] = {cat} AND ';
        } else {
            $sql.= '  WHERE ';
        }

        $sql.= '[published] = {published} AND [[blog]].[publishtime] <= {now}
                ORDER BY [[blog]].[publishtime] desc';

        if (empty($limit)) {
            $limit = $this->gadget->registry->fetch('last_entries_limit');
        }
        $result = $GLOBALS['db']->setLimit($limit);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_RECENT_ENTRIES'), _t('BLOG_NAME'));
        }

        $types = array('integer', 'integer', 'text', 'text', 'text', 'text', 'text', 'text',
                       'timestamp', 'timestamp', 'integer', 'integer', 'boolean', 'boolean');
        $result = $GLOBALS['db']->queryAll($sql, $params, $types);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_RECENT_ENTRIES'), _t('BLOG_NAME'));
        }

        return $result;
    }

    /**
     * Create ATOM struct
     *
     * @access  public
     * @param   string  $feed_type  OPTIONAL feed type
     * @return  mixed  Can return the Atom Object or Jaws_Error on error
     */
    function GetAtomStruct($feed_type = 'atom')
    {
        if (isset($this->_Atom) && is_array($this->_Atom->Entries) && count($this->_Atom->Entries) > 0) {
            return $this->_Atom;
        }

        require_once JAWS_PATH . 'include/Jaws/AtomFeed.php';
        $this->_Atom = new Jaws_AtomFeed();

        $params              = array();
        $params['published'] = true;
        $params['now']       = $GLOBALS['db']->Date();

        $sql = '
            SELECT
                [[blog]].[id],
                [user_id],
                [username],
                [email],
                [nickname],
                [title],
                [summary],
                [text],
                [fast_url],
                [[blog]].[publishtime],
                [[blog]].[updatetime],
                [clicks],
                [comments],
                [allow_comments],
                [published]
            FROM [[blog]], [[users]]
            WHERE
                [[blog]].[user_id] = [[users]].[id]
              AND
                [[blog]].[published] = {published}
              AND
                [[blog]].[publishtime] <= {now}
            ORDER BY [[blog]].[publishtime] desc';

        $limit = $this->gadget->registry->fetch('xml_limit');
        $result = $GLOBALS['db']->setLimit($limit);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_ATOMSTRUCT'), _t('BLOG_NAME'));
        }

        $types = array('integer', 'integer', 'text', 'text', 'text',
                       'text', 'text', 'text', 'text', 'timestamp', 'timestamp',
                       'integer', 'integer', 'boolean', 'boolean');
        $result = $GLOBALS['db']->queryAll($sql, $params, $types);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_ATOMSTRUCT'), _t('BLOG_NAME'));
        }

        $siteURL = $GLOBALS['app']->GetSiteURL('/');
        $url = $GLOBALS['app']->Map->GetURLFor('Blog',
                                               $feed_type == 'atom'? 'Atom' : 'RSS',
                                               array(),
                                               true);

        $this->_Atom->SetTitle($this->gadget->registry->fetch('site_name', 'Settings'));
        $this->_Atom->SetLink($url);
        $this->_Atom->SetId($siteURL);
        $this->_Atom->SetTagLine($this->gadget->registry->fetch('site_slogan', 'Settings'));
        $this->_Atom->SetAuthor($this->gadget->registry->fetch('site_author', 'Settings'),
                                $GLOBALS['app']->GetSiteURL(),
                                $this->gadget->registry->fetch('gate_email', 'Settings'));
        $this->_Atom->SetGenerator('JAWS '.$GLOBALS['app']->Registry->fetch('version'));
        $this->_Atom->SetCopyright($this->gadget->registry->fetch('copyright', 'Settings'));

        $this->_Atom->SetStyle($GLOBALS['app']->GetSiteURL('/gadgets/Blog/templates/atom.xsl'), 'text/xsl');

        $objDate = $GLOBALS['app']->loadDate();
        foreach ($result as $r) {
            $entry = new AtomEntry();
            $entry->SetTitle($r['title']);
            $post_id = empty($r['fast_url']) ? $r['id'] : $r['fast_url'];
            $url = $GLOBALS['app']->Map->GetURLFor('Blog',
                                                   'SingleView',
                                                   array('id' => $post_id),
                                                   true);
            $entry->SetLink($url);
            $entry->SetId($url);

            $summary = $r['summary'];
            $text    = $r['text'];

            // for compatibility with old versions
            $more_pos = Jaws_UTF8::strpos($text, '[more]');
            if ($more_pos !== false) {
                $summary = Jaws_UTF8::substr($text, 0, $more_pos);
                $text    = Jaws_UTF8::str_replace('[more]', '', $text);

                // Update this entry to split summary and body of post
                $this->SplitEntry($r['id'], $summary, $text);
            }

            $summary = empty($summary)? $text : $summary;
            $summary = $this->gadget->ParseText($summary);
            $text    = $this->gadget->ParseText($text);

            $entry->SetSummary($summary, 'html');
            //$entry->SetContent($text, 'html');
            $email = $r['email'];
            $entry->SetAuthor($r['nickname'], $this->_Atom->Link->HRef, $email);
            $entry->SetPublished($objDate->ToISO($r['publishtime']));
            $entry->SetUpdated($objDate->ToISO($r['updatetime']));

            $cats = $this->GetCategoriesInEntry($r['id']);
            foreach ($cats as $c) {
                $schema = $GLOBALS['app']->Map->GetURLFor('Blog', 'ShowCategory',
                                                array('id' => $c['id']), true);
                $entry->AddCategory($c['id'], $c['name'], $schema );
            }
            $this->_Atom->AddEntry($entry);

            if (!isset($last_modified) || ($last_modified < $r['updatetime'])) {
                $last_modified = $r['updatetime'];
            }
        }

        if (isset($last_modified)) {
            $this->_Atom->SetUpdated($objDate->ToISO($last_modified));
        } else {
            $this->_Atom->SetUpdated($objDate->ToISO(date('Y-m-d H:i:s')));
        }
        return $this->_Atom;
    }

    /**
     * Create ATOM of the blog
     *
     * @access  public
     * @param   bool    $write Flag that determinates if Atom file should be written to disk
     * @return  mixed   XML string or Jaws_Error on error
     */
    function MakeAtom($write = false)
    {
        $atom = $this->GetAtomStruct('atom');
        if (Jaws_Error::IsError($atom)) {
            return $atom;
        }

        if ($write) {
            if (!Jaws_Utils::is_writable(JAWS_DATA . 'xml')) {
                return new Jaws_Error(_t('BLOG_ERROR_WRITING_ATOMFILE'), _t('BLOG_NAME'));
            }

            $atom->SetLink($GLOBALS['app']->getDataURL('xml/blog.atom', false));
            ///FIXME we need to do more error checking over here
            @file_put_contents(JAWS_DATA . 'xml/blog.atom', $atom->GetXML());
            Jaws_Utils::chmod(JAWS_DATA . 'xml/blog.atom');
        }

        return $atom->GetXML();
    }

    /**
     * Create RSS of the blog
     *
     * @access  public
     * @param   bool    $write  Flag that determinates if it should returns the RSS
     * @return  mixed   Returns the RSS(string) if it was required, or Jaws_Error on error
     */
    function MakeRSS($write = false)
    {
        $atom = $this->GetAtomStruct('rss');
        if (Jaws_Error::IsError($atom)) {
            return $atom;
        }

        if ($write) {
            if (!Jaws_Utils::is_writable(JAWS_DATA . 'xml')) {
                return new Jaws_Error(_t('BLOG_ERROR_WRITING_RSSFILE'), _t('BLOG_NAME'));
            }

            $atom->SetLink($GLOBALS['app']->getDataURL('xml/blog.rss', false));
            ///FIXME we need to do more error checking over here
            @file_put_contents(JAWS_DATA . 'xml/blog.rss', $atom->ToRSS2());
            Jaws_Utils::chmod(JAWS_DATA . 'xml/blog.rss');
        }

        return $atom->ToRSS2();
    }


    /**
     * Create ATOM struct of a given category
     *
     * @access  public
     * @param   int     $category   Category ID
     * @param   string  $feed_type  OPTIONAL feed type
     * @return  mixed   Can return the Atom Object or Jaws_Error on error
     */
    function GetCategoryAtomStruct($category, $feed_type = 'atom')
    {
        $catInfo = $this->GetCategory($category);
        if (Jaws_Error::IsError($catInfo)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_CATEGORIES_ATOMSTRUCT'), _t('BLOG_NAME'));
        }

        $params              = array();
        $params['category']  = $catInfo['id'];
        $params['published'] = true;
        $params['now']       = $GLOBALS['db']->Date();

        $sql = '
            SELECT
                [[blog]].[id],
                [user_id],
                [[blog_entrycat]].[category_id],
                [username],
                [email],
                [nickname],
                [title],
                [fast_url],
                [summary],
                [text],
                [[blog]].[publishtime],
                [[blog]].[updatetime],
                [clicks],
                [comments],
                [allow_comments],
                [published]
            FROM [[blog]]
                INNER JOIN [[users]] ON [[blog]].[user_id] = [[users]].[id]
                INNER JOIN [[blog_entrycat]] ON [[blog]].[id] = [[blog_entrycat]].[entry_id]
            WHERE
                [published] = {published}
              AND
                [[blog]].[publishtime] <= {now}
              AND
                [[blog_entrycat]].[category_id] = {category}
            ORDER BY [[blog]].[publishtime] desc';

        $types = array('integer', 'integer', 'integer', 'text', 'text',
                       'text', 'text', 'text', 'text', 'text', 'timestamp', 'timestamp',
                       'integer', 'integer', 'boolean', 'boolean');
        $result = $GLOBALS['db']->queryAll($sql, $params, $types);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_CATEGORIES_ATOMSTRUCT'), _t('BLOG_NAME'));
        }

        $cid = empty($catInfo['fast_url']) ? $catInfo['id'] : Jaws_XSS::filter($catInfo['fast_url']);

        require_once JAWS_PATH . 'include/Jaws/AtomFeed.php';
        $categoryAtom = new Jaws_AtomFeed();

        $siteURL = $GLOBALS['app']->GetSiteURL('/');
        $url = $GLOBALS['app']->Map->GetURLFor('Blog',
                                               $feed_type == 'atom'? 'ShowAtomCategory' : 'ShowRSSCategory',
                                               array('id' => $cid),
                                               true);

        $categoryAtom->SetTitle($this->gadget->registry->fetch('site_name', 'Settings'));
        $categoryAtom->SetLink($url);
        $categoryAtom->SetId($siteURL);
        $categoryAtom->SetTagLine($catInfo['name']);
        $categoryAtom->SetAuthor($this->gadget->registry->fetch('site_author', 'Settings'),
                                 $siteURL,
                                 $this->gadget->registry->fetch('gate_email', 'Settings'));
        $categoryAtom->SetGenerator('JAWS '.$GLOBALS['app']->Registry->fetch('version'));
        $categoryAtom->SetCopyright($this->gadget->registry->fetch('copyright', 'Settings'));
        $categoryAtom->SetStyle($GLOBALS['app']->GetSiteURL('/gadgets/Blog/templates/atom.xsl'), 'text/xsl');

        $objDate = $GLOBALS['app']->loadDate();
        foreach ($result as $r) {
            $entry = new AtomEntry();
            $entry->SetTitle($r['title']);
            $post_id = empty($r['fast_url']) ? $r['id'] : $r['fast_url'];
            $url = $GLOBALS['app']->Map->GetURLFor('Blog', 'SingleView',
                                                   array('id' => $post_id),
                                                   true);
            $entry->SetLink($url);
            $entry->SetId($url);

            $summary = $r['summary'];
            $text    = $r['text'];

            // for compatibility with old versions
            $more_pos = Jaws_UTF8::strpos($text, '[more]');
            if ($more_pos !== false) {
                $summary = Jaws_UTF8::substr($text, 0, $more_pos);
                $text    = Jaws_UTF8::str_replace('[more]', '', $text);

                // Update this entry to split summary and body of post
                $this->SplitEntry($r['id'], $summary, $text);
            }

            $summary = empty($summary)? $text : $summary;
            $summary = $this->gadget->ParseText($summary);
            $text    = $this->gadget->ParseText($text);

            $entry->SetSummary($summary, 'html');
            $entry->SetContent($text, 'html');
            $email = $r['email'];
            $entry->SetAuthor($r['nickname'], $categoryAtom->Link->HRef, $email);
            $entry->SetPublished($objDate->ToISO($r['publishtime']));
            $entry->SetUpdated($objDate->ToISO($r['updatetime']));

            $categoryAtom->AddEntry($entry);

            if (!isset($last_modified)) {
                $last_modified = $r['updatetime'];
            }
        }

        if (isset($last_modified)) {
            $categoryAtom->SetUpdated($objDate->ToISO($last_modified));
        } else {
            $categoryAtom->SetUpdated($objDate->ToISO(date('Y-m-d H:i:s')));
        }

        return $categoryAtom;
    }

    /**
     * Create ATOM of the blog
     *
     * @access  public
     * @param   int     $categoryId     Category ID
     * @param   string  $catAtom        
     * @param   bool    $writeToDisk    Flag that determinates if Atom file should be written to disk
     * @return  mixed   Returns nothing if atom was saved, otherwise returns the ATOM in XML(string) or Jaws_Error on error
     */
    function MakeCategoryAtom($categoryId, $catAtom = null, $writeToDisk = false)
    {
        if (empty($catAtom)) {
            $catAtom = $this->GetCategoryAtomStruct($categoryId, 'atom');
            if (Jaws_Error::IsError($catAtom)) {
                return $catAtom;
            }
        }

        if ($writeToDisk) {
            if (!Jaws_Utils::is_writable(JAWS_DATA.'xml')) {
                return new Jaws_Error(_t('BLOG_ERROR_WRITING_CATEGORY_ATOMFILE'), _t('BLOG_NAME'));
            }

            $filename = basename($catAtom->Link->HRef);
            $filename = substr($filename, 0, strrpos($filename, '.')) . '.atom';
            $catAtom->SetLink($GLOBALS['app']->getDataURL('xml/' . $filename, false));
            ///FIXME we need to do more error checking over here
            @file_put_contents(JAWS_DATA . 'xml/' . $filename, $catAtom->GetXML());
            Jaws_Utils::chmod(JAWS_DATA . 'xml/' . $filename);
        }

        return $catAtom->GetXML();
    }

    /**
     * Create RSS of a given category
     *
     * @access  public
     * @param   int     $categoryId     Category ID
     * @param   string  $catAtom        
     * @param   bool    $writeToDisk    Flag that determinates if Atom file should be written to disk
     * @return  mixed   Returns the RSS(string) if it was required, or Jaws_Error on error
     */
    function MakeCategoryRSS($categoryId, $catAtom = null, $writeToDisk = false)
    {
        if (empty($catAtom)) {
            $catAtom = $this->GetCategoryAtomStruct($categoryId, 'rss');
            if (Jaws_Error::IsError($catAtom)) {
                return $catAtom;
            }
        }

        if ($writeToDisk) {
            if (!Jaws_Utils::is_writable(JAWS_DATA.'xml')) {
                return new Jaws_Error(_t('BLOG_ERROR_WRITING_CATEGORY_ATOMFILE'), _t('BLOG_NAME'));
            }

            $filename = basename($catAtom->Link->HRef);
            $filename = substr($filename, 0, strrpos($filename, '.')) . '.rss';
            $catAtom->SetLink($GLOBALS['app']->getDataURL('xml/' . $filename, false));
            ///FIXME we need to do more error checking over here
            @file_put_contents(JAWS_DATA . 'xml/' . $filename, $catAtom->ToRSS2());
            Jaws_Utils::chmod(JAWS_DATA . 'xml/' . $filename);
        }

        return $catAtom->ToRSS2();
    }

    /**
     * Create ATOM struct of recent comments
     *
     * @access  private
     * @param   string  $feed_type  OPTIONAL feed type
     * @return  object  Can return the Atom Object
     */
    function GetRecentCommentsAtomStruct($feed_type = 'atom')
    {
        $comments = $this->GetRecentComments();
        if (Jaws_Error::IsError($comments)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_COMMENTS_ATOMSTRUCT'), _t('BLOG_NAME'));
        }

        require_once JAWS_PATH . 'include/Jaws/AtomFeed.php';
        $commentAtom = new Jaws_AtomFeed();

        $siteURL = $GLOBALS['app']->GetSiteURL('/');
        $url = $GLOBALS['app']->Map->GetURLFor('Blog',
                                               $feed_type == 'atom'? 'RecentCommentsAtom' : 'RecentCommentsRSS',
                                               array(),
                                               true);

        $commentAtom->SetTitle($this->gadget->registry->fetch('site_name', 'Settings'));
        $commentAtom->SetLink($url);
        $commentAtom->SetId($siteURL);
        $commentAtom->SetAuthor($this->gadget->registry->fetch('site_author', 'Settings'),
                                $GLOBALS['app']->GetSiteURL(),
                                $this->gadget->registry->fetch('gate_email', 'Settings'));
        $commentAtom->SetGenerator('JAWS '.$GLOBALS['app']->Registry->fetch('version'));
        $commentAtom->SetCopyright($this->gadget->registry->fetch('copyright', 'Settings'));

        $commentAtom->SetStyle($GLOBALS['app']->GetSiteURL('/gadgets/Blog/templates/atom.xsl'), 'text/xsl');
        $commentAtom->SetTagLine(_t('BLOG_RECENT_COMMENTS'));

        $objDate = $GLOBALS['app']->loadDate();
        $site = preg_replace('/(.*)\/.*/i', '\\1', $commentAtom->Link->HRef);
        foreach ($comments as $c) {
            $entry_id = $c['reference'];
            $entry = new AtomEntry();
            $entry->SetTitle($c['title']);

            // So we can use the UrlMapping feature.
            $url = $GLOBALS['app']->Map->GetURLFor('Blog', 'SingleView',
                                                   array('id' => $entry_id),
                                                   true);

            $url =  $url . htmlentities('#comment' . $c['id']);
            $entry->SetLink($url);

            $id = $site . '/blog/' . $entry_id . '/' . $c['id'];
            $entry->SetId($id);
            $content = Jaws_String::AutoParagraph($c['msg_txt']);
            $entry->SetSummary($content, 'html');
            $entry->SetContent($content, 'html');
            $entry->SetAuthor($c['name'], $commentAtom->Link->HRef, $c['email']);
            $entry->SetPublished($objDate->ToISO($c['createtime']));
            $entry->SetUpdated($objDate->ToISO($c['createtime']));

            $commentAtom->AddEntry($entry);
            if (!isset($last_modified)) {
                $last_modified = $c['createtime'];
            }
        }
        if (isset($last_modified)) {
            $commentAtom->SetUpdated($objDate->ToISO($last_modified));
        } else {
            $commentAtom->SetUpdated($objDate->ToISO(date('Y-m-d H:i:s')));
        }
        return $commentAtom;
    }

    /**
     * Recent comments Atom
     *
     * @access  public
     * @return  mixed    Returns the Recent comments RSS
     */
    function GetRecentCommentsAtom()
    {
        $commAtom = $this->GetRecentCommentsAtomStruct('atom');
        if (Jaws_Error::IsError($commAtom)) {
            return $commAtom;
        }

        return $commAtom->GetXML();
    }

    /**
     * Recent comments RSS
     *
     * @access  public
     * @return  mixed    Returns the Recent comments RSS
     */
    function GetRecentCommentsRSS()
    {
        $commAtom = $this->GetRecentCommentsAtomStruct('rss');
        if (Jaws_Error::IsError($commAtom)) {
            return $commAtom;
        }

        return $commAtom->ToRSS2();
    }

    /**
     * Create ATOM struct of comments of a given entry
     *
     * @access  private
     * @param   int     $id             Post ID
     * @param   string  $feed_type      OPTIONAL feed type
     * @return  object  Can return the Atom Object
     */
    function GetPostCommentsAtomStruct($id, $feed_type = 'atom')
    {
        $comments =  $this->GetCommentsFiltered('postid', $id, 'approved', false);
        if (Jaws_Error::IsError($comments)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_POST_COMMENTS_ATOMSTRUCT'), _t('BLOG_NAME'));
        }

        require_once JAWS_PATH . 'include/Jaws/AtomFeed.php';
        $commentAtom = new Jaws_AtomFeed();

        $siteURL = $GLOBALS['app']->GetSiteURL('/');
        $url = $GLOBALS['app']->Map->GetURLFor('Blog',
                                               $feed_type == 'atom'? 'CommentsAtom' : 'CommentsRSS',
                                               array('id' => $id),
                                               true);

        $commentAtom->SetTitle($this->gadget->registry->fetch('site_name', 'Settings'));
        $commentAtom->SetLink($url);
        $commentAtom->SetId($siteURL);
        $commentAtom->SetAuthor($this->gadget->registry->fetch('site_author', 'Settings'),
                                $GLOBALS['app']->GetSiteURL(),
                                $this->gadget->registry->fetch('gate_email', 'Settings'));
        $commentAtom->SetGenerator('JAWS '.$GLOBALS['app']->Registry->fetch('version'));
        $commentAtom->SetCopyright($this->gadget->registry->fetch('copyright', 'Settings'));

        $commentAtom->SetStyle($GLOBALS['app']->GetSiteURL('/gadgets/Blog/templates/atom.xsl'), 'text/xsl');
        $commentAtom->SetTagLine(_t('BLOG_COMMENTS_ON_POST').' '.$id);

        $objDate = $GLOBALS['app']->loadDate();
        $site = preg_replace('/(.*)\/.*/i', '\\1', $commentAtom->Link->HRef);
        foreach ($comments as $c) {
            $entry_id = $c['reference'];
            $entry = new AtomEntry();
            $entry->SetTitle($c['title']);

            // So we can use the UrlMapping feature.
            $url = $GLOBALS['app']->Map->GetURLFor('Blog',
                                                   'SingleView',
                                                   array('id' => $entry_id),
                                                   true);
            $url =  $url . htmlentities('#comment' . $c['id']);
            $entry->SetLink($url);

            $id = $site . '/blog/' . $entry_id . '/' . $c['id'];
            $entry->SetId($id);
            $content = Jaws_String::AutoParagraph($c['msg_txt']);
            $entry->SetSummary($content, 'html');
            $entry->SetContent($content, 'html');
            $entry->SetAuthor($c['name'], $commentAtom->Link->HRef, $c['email']);
            $entry->SetPublished($objDate->ToISO($c['createtime']));
            $entry->SetUpdated($objDate->ToISO($c['createtime']));

            $commentAtom->AddEntry($entry);

            if (!isset($last_modified)) {
                $last_modified = $c['createtime'];
            }
        }
        if (isset($last_modified)) {
            $commentAtom->SetUpdated($objDate->ToISO($last_modified));
        } else {
            $commentAtom->SetUpdated($objDate->ToISO(date('Y-m-d H:i:s')));
        }
        return $commentAtom;
    }

    /**
     * Comments Atom of a given post
     *
     * @access  public
     * @param   int     $id     post ID
     * @return  mixed    Returns the Recent comments RSS or Jaws_Error on error
     */
    function GetPostCommentsAtom($id)
    {
        $commAtom = $this->GetPostCommentsAtomStruct($id, 'atom');
        if (Jaws_Error::IsError($commAtom)) {
            return new Jaws_Error(_t('BLOG_ERROR_WRITING_POST_COMMENTS_ATOMFILE'), _t('BLOG_NAME'));
        }

        return $commAtom->GetXML();
    }

    /**
     * Comments RSS of a given post
     *
     * @access  public
     * @param   int     $id     post ID
     * @return  mixed    Returns the Recent comments RSS or Jaws_Error on error
     */
    function GetPostCommentsRSS($id)
    {
        $commAtom = $this->GetPostCommentsAtomStruct($id, 'rss');
        if (Jaws_Error::IsError($commAtom)) {
            return new Jaws_Error(_t('BLOG_ERROR_WRITING_POST_COMMENTS_RSSFILE'), _t('BLOG_NAME'));
        }

        return $commAtom->ToRSS2();
    }

    /**
     * Get entries of a category in a given page
     *
     * @access  public
     * @param   int     $category
     * @param   int     $page
     * @return  mixed   Returns an array of entries and Jaws_Error on error
     */
    function GetEntriesByCategory($category, $page)
    {
        if ($page > 0) {
            $page = $page - 1;
        } else {
            $page = 0;
        }

        $limit = $this->gadget->registry->fetch('last_entries_limit');
        $offset = $limit * $page;
        $result = $this->GetEntries($category, null, null, $limit, $offset);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_ENTRIES_BYCATEGORY'), _t('BLOG_NAME'));
        }

        return $result;
    }

    /**
     * Increment visits counter of an entry
     *
     * @access  public
     * @param   int     $id     ID of the Entry
     * @return  bool    True if counter was successfully increment and false on error
     */
    function ViewEntry($id)
    {
        $sql = '
            UPDATE [[blog]] SET
                [clicks] = [clicks] + 1
            WHERE
                [id] = {id}';
        $result = $GLOBALS['db']->query($sql, array('id' => $id));
        if (Jaws_Error::IsError($result)) {
            return false;
        }

        return true;
    }

    /**
     * Get an entry
     *
     * @access  public
     * @param   int     $id         ID of the Entry
     * @param   bool    $published  If it is true then get the entry only if it is published
     * @return  mixed   Properties of the entry(an array) and Jaws_Error on error
     */
    function GetEntry($id, $published = false)
    {
        $params = array();
        $params['id'] = $id;
        // super admins can get/show drafted entries
        $params['published'] = (bool)$published && !$GLOBALS['app']->Session->IsSuperAdmin();

        $sql = '
            SELECT
                [[blog]].[id],
                [[blog]].[user_id],
                [username],
                [email],
                [nickname],
                [[blog]].[title],
                [summary],
                [text],
                [fast_url],
                [meta_keywords],
                [meta_description],
                [trackbacks],
                [published],
                [[blog]].[publishtime],
                [[blog]].[updatetime],
                [comments],
                [clicks],
                [allow_comments]
            FROM [[blog]]
            LEFT JOIN [[users]] ON [[blog]].[user_id] = [[users]].[id]
            WHERE';
        if (is_numeric($id)) {
            $sql.= '
                [[blog]].[id] = {id}';
        } else {
            $sql.= '
                [[blog]].[fast_url] = {id}';
        }

        if ($params['published']) {
            // entry's author can get/show drafted entries
            $params['now']  = $GLOBALS['db']->Date();
            $params['user'] = (int)$GLOBALS['app']->Session->GetAttribute('user');
            $sql .= '
              AND
                ([[blog]].[user_id] = {user} OR
                ([published] = {published} AND [[blog]].[publishtime] <= {now}))';
        }

        $types = array('integer', 'integer', 'text', 'text', 'text', 'text',
                       'text', 'text', 'text', 'text', 'text', 'text', 'boolean',
                       'timestamp', 'timestamp', 'integer', 'integer', 'boolean');
        $row = $GLOBALS['db']->queryRow($sql, $params, $types);
        if (Jaws_Error::IsError($row)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_ENTRY'), _t('BLOG_NAME'));
        }

        $entry = array();
        if (!empty($row)) {
            $entry = $row;
            require_once JAWS_PATH . 'include/Jaws/Gravatar.php';
            $entry['avatar_source'] = Jaws_Gravatar::GetGravatar($row['email']);
            $entry['categories']    = $this->GetCategoriesInEntry($row['id']);
        }

        return $entry;
    }

    /**
     * Get latest published entry ID
     *
     * @access  public
     * @return  mixed   ID of the latest published entry and false on error
     */
    function GetLatestPublishedEntryID()
    {
        $sql = '
            SELECT [id]
            FROM [[blog]]
            WHERE
                [published] = {published}
              AND
                [publishtime] <= {now}
            ORDER BY [publishtime] desc';

        $params = array();
        $params['published'] = true;
        $params['now']       = $GLOBALS['db']->Date();

        $result = $GLOBALS['db']->queryOne($sql, $params);
        if (Jaws_Error::IsError($result)) {
            return false;
        }

        return $result;
    }

    /**
     * Get last entries
     *
     * @access  public
     * @param   int     $limit
     * @return  mixed   An array of the last entries and Jaws_Error on error
     */
    function GetLastEntries($limit)
    {
        $sql = '
            SELECT
                [[blog]].[id],
                [username],
                [email],
                [nickname],
                [[blog]].[title],
                [[blog]].[fast_url],
                [summary],
                [text],
                [[users]].[nickname] as name,
                [[blog]].[publishtime],
                [[blog]].[updatetime],
                [comments],
                [clicks],
                [allow_comments],
                [[blog]].[user_id]
            FROM [[blog]]
            LEFT JOIN [[users]] ON [[blog]].[user_id] = [[users]].[id]
            ORDER BY [[blog]].[publishtime] desc';

        $result = $GLOBALS['db']->setLimit($limit);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_LAST_ENTRIES'), _t('BLOG_NAME'));
        }

        $types = array('integer', 'integer', 'text', 'text', 'text',
                       'text', 'text', 'text', 'text', 'text', 'timestamp', 'timestamp',
                       'integer', 'integer', 'boolean', 'integer');
        $result = $GLOBALS['db']->queryAll($sql, array(), $types);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_LAST_ENTRIES'), _t('BLOG_NAME'));
        }

        require_once JAWS_PATH . 'include/Jaws/Gravatar.php';
        foreach ($result as $key => $value) {
            $result[$key]['avatar_source'] = Jaws_Gravatar::GetGravatar($value['email']);
        }

        return $result;
    }

    /**
     * Get an month/year where exists entries
     *
     * @access  public
     * @return  mixed   An array of relations between months and years of the blog and Jaws_Error on error
     */
    function GetMonthsEntries()
    {
        $GLOBALS['db']->dbc->loadModule('Function', null, true);

        $year  = $GLOBALS['db']->dbc->function->substring('[[blog]].[publishtime]', 1, 4);
        $month = $GLOBALS['db']->dbc->function->substring('[[blog]].[publishtime]', 6, 2);

        $sql = "
            SELECT
                $month as month,
                $year as year
            FROM [[blog]]
            GROUP BY
                $month,
                $year,
                [publishtime]
            ORDER BY [publishtime] desc";

        $result = $GLOBALS['db']->queryAll($sql);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_MONTH_ENTRIES'), _t('BLOG_NAME'));
        }

        return $result;
    }

    /**
     * Verify if an entry exists
     *
     * @access  public
     * @param   int     $post_id    The entry ID (ID or fast_URL, string)
     * @return  bool    True if entry exists, else, false.
     */
    function DoesEntryExists($post_id)
    {
        $params       = array();
        $params['id'] = $post_id;

        $sql  = 'SELECT COUNT([id]) FROM [[blog]] WHERE ';
        $sql .=  is_numeric($post_id) ? '[id] = {id}' : '[fast_url] = {id}';

        $count = $GLOBALS['db']->queryOne($sql, $params);
        if (Jaws_Error::IsError($count)) {
            return false;
        }

        if ($count > 0) {
            return true;
        }

        return false;
    }

    /**
     * Get trackbacks
     *
     * @access  public
     * @param   int     $id     ID of the Entry
     * @return  mixed   A list of the trackbacks, if blog is not using trackback returns true,
     *                  but if blog is using trackback but was not fetched correctly will returns false or Jaws_Error on error
     */
    function GetTrackbacks($id)
    {
        $params           = array();
        $params['id']     = $id;
        $params['status'] = 'approved';
        if ($this->gadget->registry->fetch('trackback') == 'true') {
            $sql = '
                SELECT
                    [id],
                    [parent_id],
                    [url],
                    [title],
                    [excerpt],
                    [blog_name],
                    [createtime]
                FROM [[blog_trackback]]
                WHERE 
                    [parent_id] = {id} 
                  AND
                    [status]    = {status}
                ORDER BY [createtime] asc';

            $result = $GLOBALS['db']->queryAll($sql, $params);
            if (Jaws_Error::IsError($result)) {
                return new Jaws_Error(_t('BLOG_ERROR_GETTING_TRACKBACKS'), _t('BLOG_NAME'));
            }

            $entries = array();
            foreach ($result as $r) {
                $r['createtime'] = $r['createtime'];
                $entries[] = $r;
            }

            return $entries;
        }

        return true;
    }

    /**
     * Get trackbacks
     *
     * @access  public
     * @param   int     $id     ID of the Trackback
     * @return  mixed   Properties of a trackback and Jaws_Error on error
     */
    function GetTrackback($id)
    {
        $params           = array();
        $params['id']     = $id;
        $sql = '
            SELECT
                [id],
                [parent_id],
                [url],
                [title],
                [excerpt],
                [blog_name],
                [ip],
                [createtime],
                [updatetime]
            FROM [[blog_trackback]]
            WHERE [id] = {id}';

        $result = $GLOBALS['db']->queryRow($sql, $params);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('BLOG_ERROR_GETTING_TRACKBACKS'), _t('BLOG_NAME'));
        }

        $entries = array(
                          'id'         => isset($result['id']) ? $result['id'] : null,
                          'parent_id'  => isset($result['parent_id']) ? $result['parent_id'] : null,
                          'url'        => isset($result['url']) ? $result['url'] : null,
                          'title'      => isset($result['title']) ? $result['title'] : null,
                          'excerpt'    => isset($result['excerpt']) ? $result['excerpt'] : null,
                          'blog_name'  => isset($result['blog_name']) ? $result['blog_name'] : null,
                          'ip'         => isset($result['ip']) ? $result['ip'] : null,
                          'createtime' => isset($result['createtime']) ? $result['createtime'] : null,
                          'updatetime' => isset($result['updatetime']) ? $result['updatetime'] : null
                          );
        
        return $entries;
    }

    /**
     * Get next/previous published entry
     * NOP = next or previous
     * 
     * @access  public
     * @param   int     $id         ID of the Entry
     * @param   string  $direction  OPTIONAL direction
     * @return  bool    Properties of the entry(an array) and false on error
     */
    function GetNOPEntry($id, $direction = 'next')
    {
        $options = array(
            'next' => array(
                'sign' => '>',
                'direction' => 'ASC',
            ),
            'previous' => array(
                'sign' => '<',
                'direction' => 'DESC',
            )
        );

        if (!array_key_exists($direction, $options)) {
            $option = $options['next'];
        } else {
            $option = $options[$direction];
        }

        $params              = array();
        $params['id']        = $id;
        $params['published'] = true;
        $params['now']       = $GLOBALS['db']->Date();

        $sql = "
            SELECT
                [id], [title], [fast_url]
            FROM [[blog]]
            WHERE
                [[blog]].[id] {$option['sign']} {id}
              AND
                [published] = {published}
              AND
                [publishtime] <= {now}
            ORDER BY [id] {$option['direction']}";

        $result = $GLOBALS['db']->setLimit(1);
        if (Jaws_Error::IsError($result)) {
            return false;
        }

        $row = $GLOBALS['db']->queryRow($sql, $params);
        if (Jaws_Error::IsError($row)) {
            return false;
        }

        return $row;
    }

    /**
     * Get the fast url
     *
     * @access  public
     * @param   string  $fasturl    The fastURL of entry
     * @return  mixed   An array contains entry info and false otherwise
     */
    function GetFastURL($fasturl)
    {
        $params = array();
        $params['fasturl'] = $fasturl;

        $sql = '
            SELECT
                [id], [title], [fast_url]
            FROM [[blog]]
            WHERE [fast_url] = {fasturl}';

        $result = $GLOBALS['db']->queryRow($sql, $params);
        if (Jaws_Error::IsError($result)) {
            return false;
        }

        return $result;
    }

    /**
     * Create a new trackback
     *
     * @access  public
     * @param   int     $parent_id      ID of the entry
     * @param   string  $url            URL of the trackback
     * @param   string  $title          Title of the trackback
     * @param   string  $excerpt        The Excerpt
     * @param   string  $blog_name      The name of the Blog
     * @param   string  $ip             The sender ip address
     * @return  mixed   True if trackback was successfully added, if not, returns Jaws_Error
     */
    function NewTrackback($parent_id, $url, $title, $excerpt, $blog_name, $ip)
    {
        $params              = array();
        $params['parent_id'] = $parent_id;
        $params['url']       = strip_tags($url);
        $params['title']     = strip_tags($title);
        $params['excerpt']   = strip_tags($excerpt);
        $params['blog_name'] = strip_tags($blog_name);
        $params['ip']        = $ip;
        $params['status']    = $this->gadget->registry->fetch('trackback_status');

        if ($this->gadget->registry->fetch('trackback') == 'true') {
            if (!$this->DoesEntryExists($parent_id)) {
                return new Jaws_Error(_t('BLOG_ERROR_DOES_NOT_EXISTS'), _t('BLOG_NAME'));
            }

            // lets only load it if it's actually needed
            $params['now'] = $GLOBALS['db']->Date();

            $sql = '
                SELECT
                    [id]
                FROM [[blog_trackback]]
                WHERE
                    [parent_id] = {parent_id}
                  AND
                    url = {url}';
            $id = $GLOBALS['db']->queryOne($sql, $params);
            if (!Jaws_Error::IsError($id) && !empty($id)) {
                $params['id'] = $id;
                $sql = '
                    UPDATE [[blog_trackback]] SET
                        [title]      = {title},
                        [excerpt]    = {excerpt},
                        [blog_name]  = {blog_name},
                        [updatetime] = {now}
                    WHERE [id] = {id}';
            } else {
                $sql = '
                    INSERT INTO [[blog_trackback]]
                        ([parent_id], [url], [title], [excerpt], [blog_name], [ip], 
                         [status], [createtime], [updatetime])
                    VALUES
                        ({parent_id}, {url}, {title}, {excerpt}, {blog_name}, {ip}, 
                         {status}, {now}, {now})';
            }

            $result = $GLOBALS['db']->query($sql, $params);
            if (Jaws_Error::IsError($result)) {
                return new Jaws_Error(_t('BLOG_ERROR_TRACKBACK_NOT_ADDED'), _t('BLOG_NAME'));
            }

            return true;
        }

        return true;
    }

    /**
     * Generates a tag cloud
     *
     * @access  public
     * @return  mixed   An array on success and Jaws_Error in case of errors
     */
    function CreateTagCloud()
    {
        $sql = '
            SELECT
                COUNT([category_id]) as howmany, [name], [fast_url], [category_id]
            FROM [[blog_entrycat]]
            INNER JOIN [[blog_category]] ON [category_id] = [id]
            GROUP BY [category_id], [name], [fast_url]
            ORDER BY [name]';

        $types = array('integer', 'text', 'text', 'integer');
        $res = $GLOBALS['db']->queryAll($sql, array(), $types);
        if (Jaws_Error::isError($res)) {
            return new Jaws_Error(_t('BLOG_ERROR_TAGCLOUD_CREATION_FAILED'), _t('BLOG_NAME'));
        }

        return $res;
    }

    /**
     * Get entries in a given page (page size = last_entries_limit)
     *
     * @access  public
     * @param   int     $cat            category
     * @param   int     $page           page
     * @param   array   $condition      conditions array
     * @param   array   $extraparams    extra params array
     * @return  array  An array with the entries
     */
    function GetEntriesAsPage($cat = null, $page = 0, $condition = null, $extraparams = null)
    {
        if ($page > 0) {
            $page = $page - 1;
        } else {
            $page = 0;
        }

        $limit = $this->gadget->registry->fetch('last_entries_limit');
        $offset = $limit * $page;

        $res = $this->GetEntries($cat, $condition, $extraparams, $limit, $offset);

        return $res;
    }

    /**
     * Get number of pages limited by last_entries_limit
     *
     * @access  public
     * @param   int $category   category iD
     * @return  int number of pages
     */
    function GetNumberOfPages($category = null)
    {
        $blogTable = Jaws_ORM::getInstance()->table('blog');
        $blogTable->select('count(blog.id)');
        if (!empty($category)) {
            $blogTable->join('blog_entrycat', 'blog.id', 'blog_entrycat.entry_id', 'left');
            $blogTable->where('blog_entrycat.category_id', (int)$category)->and();
        }

        $blogTable->where('published', true)->and()->where('publishtime', $GLOBALS['db']->Date(), '<=');
        $howmany = $blogTable->fetchOne();
        return Jaws_Error::IsError($howmany)? 0 : $howmany;
    }

    /**
     * Get number of date's pages
     *
     * @access  public
     * @param   string  $min_date   minimum date
     * @param   string  $max_date   maximum date
     * @return  int number of pages
     */
    function GetDateNumberOfPages($min_date, $max_date)
    {
        $blogTable = Jaws_ORM::getInstance()->table('blog');
        $blogTable->select('count(blog.id)');
        $blogTable->where('published', true)->and();
        $blogTable->where('publishtime', $min_date, '>=')->and()->where('publishtime', $max_date, '<');
        $howmany = $blogTable->fetchOne();
        return Jaws_Error::IsError($howmany)? 0 : $howmany;
    }

    /**
     * Get number of author's pages
     *
     * @access  public
     * @param   string  $user   username
     * @return  int number of pages
     */
    function GetAuthorNumberOfPages($user)
    {
        $blogTable = Jaws_ORM::getInstance()->table('blog');
        $blogTable->select('count(blog.id)');
        $blogTable->join('users', 'blog.user_id', 'users.id', 'left');
        $blogTable->where('published', true)->and()->where('publishtime', $GLOBALS['db']->Date(), '<=');
        if (is_numeric($user)) {
            $blogTable->and()->where('users.id', $user);
        } else {
            $blogTable->and()->where('users.username', $user);
        }
        $howmany = $blogTable->fetchOne();
        return Jaws_Error::IsError($howmany)? 0 : $howmany;
    }

    /**
     * Get number of category's pages
     *
     * @access  public
     * @param   int     $category   category iD
     * @return  int number of pages
     */
    function GetCategoryNumberOfPages($category)
    {
        $blogTable = Jaws_ORM::getInstance()->table('blog');
        $blogTable->select('count(blog.id)');
        $blogTable->join('blog_entrycat', 'blog.id', 'blog_entrycat.entry_id', 'left');
        $blogTable->where('published', true)->and()->where('publishtime', $GLOBALS['db']->Date(), '<=');
        $blogTable->and()->where('blog_entrycat.category_id', $category);
        $howmany = $blogTable->fetchOne();
        return Jaws_Error::IsError($howmany)? 0 : $howmany;
    }

    /**
     * Saves an incoming pingback as a Comment
     *
     * @access  public
     * @param   int     $postID    Post ID
     * @param   string  $sourceURI Who's pinging?
     * @param   string  $permalink Target URI (of post)
     * @param   string  $title     Title of who's pinging (<title>..)
     * @param   string  $content   has the context, from exact target link position (optional)
     */
    function SavePingback($postID, $sourceURI, $permalink, $title, $content)
    {
        $sourceURI = strip_tags($sourceURI);
        $permalink = strip_tags($permalink);

        if (empty($title)) {
            $title   = _t('BLOG_PINGBACK_DEFAULT_TITLE', $sourceURI);
        }

        if (empty($content)) {
            $content = _t('BLOG_PINGBACK_DEFAULT_CONTENT', $sourceURI);
        }

        /**
         * TODO: Find some other default values for pingbacks/trackbacks
         */
        $email = $this->gadget->registry->fetch('gate_email', 'Settings');
        $name  = $this->gadget->registry->fetch('site_author', 'Settings');
        $ip    = $_SERVER['REMOTE_ADDR'];

        $status = $this->gadget->registry->fetch('comment_status');
        $cModel = $GLOBALS['app']->LoadGadget('Comments', 'Model', 'EditComments');
        $res = $cModel->insertComment(
            $this->gadget->name, $postID, 'Pingback', $name, $email, $sourceURI,
            $content, $ip, $permalink, $status
        );
    }

    /**
     * Get entry pager numbered links
     *
     * @access  public
     * @param   int     $page      Current page number
     * @param   int     $page_size Entries count per page
     * @param   int     $total     Total entries count
     * @return  array   array with numbers of pages
     */
    function GetEntryPagerNumbered($page, $page_size, $total)
    {
        $tail = 1;
        $paginator_size = 4;
        $pages = array();
        if ($page_size == 0) {
            return $pages;
        }

        $npages = ceil($total / $page_size);

        if ($npages < 2) {
            return $pages;
        }

        // Previous
        if ($page == 1) {
            $pages['previous'] = false;
        } else {
            $pages['previous'] = $page - 1;
        }

        if ($npages <= ($paginator_size + $tail)) {
            for ($i = 1; $i <= $npages; $i++) {
                if ($i == $page) {
                    $pages['current'] = $i;
                } else {
                    $pages[$i] = $i;
                }
            }
        } elseif ($page < $paginator_size) {
            for ($i = 1; $i <= $paginator_size; $i++) {
                if ($i == $page) {
                    $pages['current'] = $i;
                } else {
                    $pages[$i] = $i;
                }
            }

            $pages['separator2'] = true;

            for ($i = $npages - ($tail - 1); $i <= $npages; $i++) {
                $pages[$i] = $i;
            }
            
        } elseif ($page > ($npages - $paginator_size + $tail)) {
            for ($i = 1; $i <= $tail; $i++) {
                $pages[$i] = $i;
            }

            $pages['separator1'] = true;

            for ($i = $npages - $paginator_size + ($tail - 1); $i <= $npages; $i++) {
                if ($i == $page) {
                    $pages['current'] = $i;
                } else {
                    $pages[$i] = $i;
                }
            }
        } else {
            for ($i = 1; $i <= $tail; $i++) {
                $pages[$i] = $i;
            }

            $pages['separator1'] = true;

            $start = floor(($paginator_size - $tail)/2);
            $end = ($paginator_size - $tail) - $start;
            for ($i = $page - $start; $i < $page + $end; $i++) {
                if ($i == $page) {
                    $pages['current'] = $i;
                } else {
                    $pages[$i] = $i;
                }
            }

            $pages['separator2'] = true;

            for ($i = $npages - ($tail - 1); $i <= $npages; $i++) {
                $pages[$i] = $i;
            }
            
        }

        // Next
        if ($page == $npages) {
            $pages['next'] = false;
        } else {
            $pages['next'] = $page + 1;
        }

        $pages['total'] = $total;

        return $pages;
    }

    /**
     * Get popular posts
     *
     * @access  public
     * @return  mixed   List of popular posts or Jaws_Error on error
     */
    function GetPopularPosts()
    {
        $blogTable = Jaws_ORM::getInstance()->table('blog');
        $blogTable->limit($this->gadget->registry->fetch('popular_limit'), 0);
        $blogTable->select(
            'blog.id:integer', 'blog.user_id:integer', 'blog.title', 'blog.fast_url', 'summary',
            'text', 'clicks:integer', 'comments:integer', 'allow_comments', 'username', 'nickname',
            'blog.publishtime:timestamp', 'blog.updatetime:timestamp'
        );
        $blogTable->join('users', 'blog.user_id', 'users.id', 'left');
        $blogTable->where('published', true)->and()->where('publishtime', $GLOBALS['db']->Date(), '<=');
        $blogTable->orderBy('clicks desc');
        return $blogTable->fetchAll();
    }

    /**
     * Get posts authors
     *
     * @access  public
     * @return  mixed   List of posts authors or Jaws_Error on error
     */
    function GetPostsAuthors()
    {
        $blogTable = Jaws_ORM::getInstance()->table('blog');
        $blogTable->limit($this->gadget->registry->fetch('popular_limit'), 0);
        $blogTable->select('user_id', 'username', 'nickname', 'count(blog.id) as howmany');
        $blogTable->join('users', 'blog.user_id', 'users.id', 'left');
        $blogTable->groupBy('user_id', 'username', 'nickname');
        $blogTable->where('published', true)->and()->where('publishtime', $GLOBALS['db']->Date(), '<=');
        $blogTable->orderBy('user_id');
        return $blogTable->fetchAll();
    }

    /**
     * Temporary function for updating split summary and body of entry
     *
     * @access  public
     * @param   int     $id         ID of the Entry
     * @param   string  $summary    Summary of the entry
     * @param   string  $text       Main text of the entry
     * @return  bool    True if counter was successfully increment and false on error
     */
    function SplitEntry($id, &$summary, &$text)
    {
        $blogTable = Jaws_ORM::getInstance()->table('blog');
        $blogTable->update(array('summary' => $summary, 'text' => $text));
        return $blogTable->where('id', $id)->exec();
    }

}