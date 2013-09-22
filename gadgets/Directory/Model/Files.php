<?php
/**
 * Directory Gadget
 *
 * @category    GadgetModel
 * @package     Directory
 * @author      Mohsen Khahani <mkhahani@gmail.com>
 * @copyright   2013 Jaws Development Group
 * @license     http://www.gnu.org/copyleft/gpl.html
 */
class Directory_Model_Files extends Jaws_Gadget_Model
{
    /**
     * Fetches list of files including shared files
     *
     * @access  public
     * @param   int     $parent  Restricts results to a specified node
     * @return  array   Array of files or Jaws_Error on error
     */
    function GetFiles($parent = null, $user = null,
        $shared = null, $foreign = null, $is_dir = null, $query = null)
    {
        $access = ($user === null)? null : $this->CheckAccess($parent, $user);
        if ($access === false) {
            return array();
        }

        $table = Jaws_ORM::getInstance()->table('directory as dir');
        $table->select('dir.id', 'parent', 'user', 'is_dir:boolean', 'title',
            'description', 'filename', 'filetype', 'filesize', 'dir.url', 'shared:boolean',
            'dir.public:boolean', 'owner', 'reference', 'createtime', 'updatetime', 'users.username');
        $table->join('users', 'owner', 'users.id');

        if ($parent !== null){
            $table->where('parent', $parent)->and();
        }

        if ($access !== true && $user !== null){
            $table->where('user', $user)->and();
        }

        if ($shared !== null){
            $table->where('shared', $shared)->and();
        }

        if ($foreign !== null){
            $flag = $foreign? '<>' : '=';
            $table->where('user', $table->expr('owner'), $flag)->and();
        }

        if ($is_dir !== null){
            $table->where('is_dir', $is_dir)->and();
        }

        $query = "%$query%";
        if ($query !== null){
            $table->openWhere('title', $query, 'like')->or();
            $table->where('description', $query, 'like')->or();
            $table->closeWhere('filename', $query, 'like');
        }

        return $table->orderBy('is_dir desc', 'title asc')->fetchAll();
    }

    /**
     * Fetches data of a file or directory
     *
     * @access  public
     * @param   int     $id  File ID
     * @return  mixed   Array of file data or Jaws_Error on error
     */
    function GetFile($id)
    {
        $table = Jaws_ORM::getInstance()->table('directory as dir');
        // $table->select('dir.id', 'parent', 'user', 'is_dir:boolean', 'title',
            // 'description', 'filename', 'filetype', 'filesize', 'dir.url', 'shared:boolean',
            // 'dir.public:boolean', 'owner', 'reference', 'createtime', 'updatetime', 'users.username');
        // $table->join('users', 'owner', 'users.id');
        $table->select('id', 'parent', 'user', 'is_dir:boolean', 'title',
            'description', 'filename', 'filetype', 'filesize', 'url', 'shared:boolean',
            'public:boolean', 'owner', 'reference', 'createtime', 'updatetime');
        return $table->where('dir.id', $id)->fetchRow();
    }

    /**
     * Checks user access to files including shared files
     *
     * @access  public
     * @param   int     $id  File ID
     * @return  bool    True or false
     */
    function CheckAccess($id, $user)
    {
        if (empty($id)) {
            return null;
        } else {
            $table = Jaws_ORM::getInstance()->table('directory');
            $table->select('user:integer', 'parent:integer');
            $file = $table->where('id', $id)->fetchRow();
            if ($file['user'] === $user) {
                return true;
            }
        }

        // Check for shared files
        $table = Jaws_ORM::getInstance()->table('directory');
        $table->select('count(id):integer');
        $table->where('user', $user)->and();
        $table->where('reference', $id);
        $count = $table->fetchOne();
        if ($count > 0) {
            return true;
        }

        if ($file['parent'] !== 0) {
            return $this->CheckAccess($file['parent'], $user);
        }

        return false;
    }

    /**
     * Fetches path of a file/directory
     *
     * @access  public
     * @param   int     $id     File ID
     * @param   array   $path   Directory hierarchy
     * @return  void
     */
    function GetPath($id, &$path)
    {
        $table = Jaws_ORM::getInstance()->table('directory');
        $table->select('id', 'parent', 'title');
        $parent = $table->where('id', $id)->fetchRow();
        if (!empty($parent)) {
            $path[] = array('id' => $parent['id'], 'title' => $parent['title']);
            $this->GetPath($parent['parent'], $path);
        }
    }

    /**
     * Inserts a new file/directory
     *
     * @access  public
     * @param   array   $data    File data
     * @return  mixed   Query result
     */
    function Insert($data)
    {
        $data['createtime'] = $data['updatetime'] = time();
        $table = Jaws_ORM::getInstance()->table('directory');
        return $table->insert($data)->exec();
    }

    /**
     * Updates file/directory
     *
     * @access  public
     * @param   int     $id     File ID
     * @param   array   $data   File data
     * @return  mixed   Query result
     */
    function Update($id, $data)
    {
        $table = Jaws_ORM::getInstance()->table('directory');
        return $table->update($data)->where('id', $id)->exec();
    }

    /**
     * Deletes file/directory
     *
     * @access  public
     * @param   int     $id  File ID
     * @return  mixed   Query result
     */
    function Delete($id)
    {
        $table = Jaws_ORM::getInstance()->table('directory');
        $table->delete()->where('id', $id);
        $table->or()->where('reference', $id);
        return $table->exec();
    }

    /**
     * Updates parent of the file/directory
     *
     * @access  public
     * @param   int     $id      File ID
     * @param   int     $parent  New file parent
     * @return  mixed   Query result
     */
    function Move($id, $parent)
    {
        $table = Jaws_ORM::getInstance()->table('directory');
        $table->update(array('parent' => $parent));
        return $table->where('id', $id)->exec();
    }

    /**
     * Updates shortcuts
     *
     * @access  public
     * @param   int     $ref    File reference ID
     * @param   array   $data   File data
     * @return  mixed   Query result
     */
    function UpdateShortcuts($ref, $data)
    {
        $table = Jaws_ORM::getInstance()->table('directory');
        return $table->update($data)->where('reference', $ref)->exec();
    }
}