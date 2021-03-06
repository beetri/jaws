<?php
/**
 * Users Core Gadget
 *
 * @category    Gadget
 * @package     Users
 * @author      Ali Fazelzadeh <afz@php.net>
 * @author      Mojtaba Ebrahimi <ebrahimi@zehneziba.ir>
 * @copyright   2013-2014 Jaws Development Group
 * @license     http://www.gnu.org/copyleft/lesser.html
 */
class Users_Actions_Groups extends Users_Actions_Default
{
    /**
     * Prepares a form for manage user's groups
     *
     * @access  public
     * @return  string  XHTML template of a form
     */
    function Groups()
    {
        if (!$GLOBALS['app']->Session->Logged()) {
            Jaws_Header::Location(
                $this->gadget->urlMap(
                    'LoginBox',
                    array('referrer'  => bin2hex(Jaws_Utils::getRequestURL(true)))
                )
            );
        }

        $this->gadget->CheckPermission('ManageUserGroups');
        $this->AjaxMe('index.js');
        $response = $GLOBALS['app']->Session->PopResponse('Users.Groups');
        $user = $GLOBALS['app']->Session->GetAttribute('user');
        $jUser = new Jaws_User;
        $groups = $jUser->GetGroups($user);

        // Load the template
        $tpl = $this->gadget->template->load('Groups.html');
        $tpl->SetBlock('groups');

        if (!empty($response)) {
            $tpl->SetVariable('type', $response['type']);
            $tpl->SetVariable('text', $response['text']);
        }

        $tpl->SetVariable('title', _t('USERS_GROUPS'));
        $tpl->SetVariable('menubar', $this->MenuBar('Groups'));
        $tpl->SetVariable('submenubar', $this->SubMenuBar('Groups', array('Groups', 'AddGroup')));
        $tpl->SetVariable('base_script', BASE_SCRIPT);

        $tpl->SetVariable('lbl_name', _t('GLOBAL_NAME'));
        $tpl->SetVariable('lbl_title', _t('GLOBAL_TITLE'));

        foreach ($groups as $group) {
            $tpl->SetBlock('groups/group');
            $tpl->SetVariable('id', $group['id']);
            $tpl->SetVariable('url', $this->gadget->urlMap('ManageGroup', array('gid' => $group['id'])));
            $tpl->SetVariable('name', $group['name']);
            $tpl->SetVariable('title', $group['title']);
            $tpl->ParseBlock('groups/group');
        }

        $tpl->SetVariable('lbl_actions', _t('GLOBAL_ACTIONS'));
        $tpl->SetVariable('lbl_no_action', _t('GLOBAL_NO_ACTION'));

        $tpl->SetVariable('lbl_delete', _t('GLOBAL_DELETE'));
        $tpl->SetVariable('icon_filter', STOCK_SEARCH);
        $tpl->SetVariable('icon_ok', STOCK_OK);
        $tpl->SetVariable('lbl_add_group', _t('USERS_ADD_GROUP'));
        $tpl->SetVariable('url_add_group', $this->gadget->urlMap('UserGroupUI'));

        $tpl->ParseBlock('groups');
        return $tpl->Get();
    }

    /**
     * User's group UI
     *
     * @access  public
     * @param   int     $gid  Exiting group ID for editing
     * @return  string  XHTML template of a form
     */
    function UserGroupUI($gid = null)
    {
        if (!$GLOBALS['app']->Session->Logged()) {
            Jaws_Header::Location(
                $this->gadget->urlMap(
                    'LoginBox',
                    array('referrer'  => bin2hex(Jaws_Utils::getRequestURL(true)))
                )
            );
        }

        $this->gadget->CheckPermission('ManageUserGroups');
        $this->AjaxMe('index.js');

        // Load the template
        $tpl = $this->gadget->template->load('Groups.html');
        $tpl->SetBlock('add_group');

        // edit an user group
        if (!empty($gid)) {
            $jUser = new Jaws_User;
            $group = $jUser->GetGroup((int)$gid, $GLOBALS['app']->Session->GetAttribute('user'));

            $tpl->SetVariable('gid', $gid);
            $tpl->SetVariable('name', $group['name']);
            $tpl->SetVariable('group_title', $group['title']);
            $tpl->SetVariable('description', $group['description']);
        }

        $tpl->SetVariable('menubar', $this->MenuBar('Groups'));
        if (empty($gid)) {
            $tpl->SetVariable('title', _t('USERS_ADD_GROUP'));
            $tpl->SetVariable(
                'submenubar',
                $this->SubMenuBar('AddGroup', array('Groups', 'AddGroup'))
            );
        } else {
            $tpl->SetVariable('title', _t('USERS_EDIT_GROUP'));
            $tpl->SetVariable(
                'submenubar',
                $this->SubMenuBar('EditGroup', array('Members', 'EditGroup'), array('gid' => $gid))
            );
        }

        $tpl->SetVariable('lbl_name', _t('GLOBAL_NAME'));
        $tpl->SetVariable('lbl_title', _t('GLOBAL_TITLE'));
        $tpl->SetVariable('lbl_description', _t('GLOBAL_DESCRIPTION'));
        $tpl->SetVariable('lbl_yes', _t('GLOBAL_YES'));
        $tpl->SetVariable('lbl_no', _t('GLOBAL_NO'));
        $tpl->SetVariable('save', _t('GLOBAL_SAVE'));

        $tpl->ParseBlock('add_group');
        return $tpl->Get();
    }

    /**
     * Edit user's group
     *
     * @access  public
     * @return  string  XHTML template of a form
     */
    function EditUserGroup()
    {
        $gid = jaws()->request->fetch('gid', 'get');
        return $this->UserGroupUI($gid);
    }

    /**
     * Add an new group
     *
     * @access  public
     * @return  void
     */
    function AddGroup()
    {
        $this->gadget->CheckPermission('ManageUserGroups');

        $post = jaws()->request->fetch(array('gid', 'name', 'title', 'description'), 'post');
        $user = $GLOBALS['app']->Session->GetAttribute('user');

        $jUser = new Jaws_User;
        // Update group
        if(!empty($post['gid'])) {
            $res = $jUser->UpdateGroup($post['gid'], $post, $user);
        // Add new group
        } else {
            unset($post['gid']);
            $res = $jUser->AddGroup($post, $user);
        }

        if (Jaws_Error::isError($res)) {
            $GLOBALS['app']->Session->PushResponse(
                $res->getMessage(),
                'Users.Groups',
                RESPONSE_ERROR
            );
        } elseif ($res == true) {
            $GLOBALS['app']->Session->PushResponse(
                _t('USERS_GROUPS_CREATED', $post['title']),
                'Users.Groups',
                RESPONSE_NOTICE
            );
        }
        Jaws_Header::Location($this->gadget->urlMap('Groups'));
    }

    /**
     * Delete user's group(s)
     *
     * @access  public
     * @return  void
     */
    function DeleteGroups()
    {
        $this->gadget->CheckPermission('ManageUserGroups');

        $ids = jaws()->request->fetch('group_checkbox:array', 'post');
        $user = $GLOBALS['app']->Session->GetAttribute('user');

        if (!empty($ids)) {
            $jUser = new Jaws_User;
            foreach($ids as $id) {
                // TODO: improve performance
                $res= $jUser->DeleteGroup($id, $user);
                if (Jaws_Error::IsError($res)) {
                    $GLOBALS['app']->Session->PushResponse(
                        $res->getMessage(),
                        'Users.Groups',
                        RESPONSE_ERROR
                    );
                    break;
                }
            }

            if (!isset($res)) {
                $GLOBALS['app']->Session->PushResponse(
                    _t('USERS_GROUP_DELETED'),
                    'Users.Groups',
                    RESPONSE_NOTICE
                );
            }
        }

        Jaws_Header::Location($this->gadget->urlMap('Groups'));
    }


    /**
     * Add an user to a group
     *
     * @access  public
     * @return  void
     */
    function AddUserToGroup()
    {
        $this->gadget->CheckPermission('ManageUserGroups');

        $post = jaws()->request->fetch(array('gid', 'users'), 'post');
        $user = $GLOBALS['app']->Session->GetAttribute('user');

        $jUser = new Jaws_User;
        $res = $jUser->AddUserToGroup($post['users'], $post['gid'], $user);

        if ($res == true) {
            $GLOBALS['app']->Session->PushResponse(
                _t('USERS_GROUP_ADDED_USER'),
                'Users.GroupMember',
                RESPONSE_NOTICE
            );
        } else {
            $GLOBALS['app']->Session->PushResponse(
                _t('USERS_GROUP_CANNOT_ADD_USER'),
                'Users.GroupMember',
                RESPONSE_ERROR
            );
        }
        Jaws_Header::Location($this->gadget->urlMap('ManageGroup', array('gid' => $post['gid'])));
    }

    /**
     * Remove users from a group
     *
     * @access  public
     * @return  void
     */
    function RemoveUserFromGroup()
    {
        $this->gadget->CheckPermission('ManageUserGroups');

        $post = jaws()->request->fetch(array('gid', 'member_checkbox:array'), 'post');
        $user = $GLOBALS['app']->Session->GetAttribute('user');

        if (!empty($post['member_checkbox'])) {
            $jUser = new Jaws_User;
            // TODO: improve performance
            foreach ($post['member_checkbox'] as $member) {
                $res = $jUser->DeleteUserFromGroup($member, $post['gid'], $user);
                if (Jaws_Error::IsError($res)) {
                    $GLOBALS['app']->Session->PushResponse(
                        $res->getMessage(),
                        'Users.GroupMember',
                        RESPONSE_ERROR
                    );
                }
            }

            if (!isset($res)) {
                $GLOBALS['app']->Session->PushResponse(
                    _t('USERS_GROUP_REMOVED_USER'),
                    'Users.GroupMember',
                    RESPONSE_NOTICE
                );
            }
        }

        Jaws_Header::Location($this->gadget->urlMap('ManageGroup', array('gid' => $post['gid'])));
    }


    /**
     * Manage group
     *
     * @access  public
     * @return  string  XHTML template of a form
     */
    function ManageGroup()
    {
        if (!$GLOBALS['app']->Session->Logged()) {
            Jaws_Header::Location(
                $this->gadget->urlMap(
                    'LoginBox',
                    array('referrer'  => bin2hex(Jaws_Utils::getRequestURL(true)))
                )
            );
        }

        $this->gadget->CheckPermission('ManageUserGroups');

        $gid = (int) jaws()->request->fetch('gid', 'get');
        $user = $GLOBALS['app']->Session->GetAttribute('user');

        // Load the template
        $tpl = $this->gadget->template->load('Groups.html');
        $tpl->SetBlock('manage_group');

        $jUser = new Jaws_User;
        $group = $jUser->GetGroup($gid, $user);

        $response = $GLOBALS['app']->Session->PopResponse('Users.GroupMember');
        if (!empty($response)) {
            $tpl->SetVariable('type', $response['type']);
            $tpl->SetVariable('text', $response['text']);
        }

        $tpl->SetVariable('title', _t('USERS_MANAGE_GROUPS', $group['title']));
        $tpl->SetVariable('menubar', $this->MenuBar('Groups'));
        $tpl->SetVariable(
            'submenubar',
            $this->SubMenuBar('Members', array('Members', 'EditGroup'), array('gid' => $gid))
        );
        $tpl->SetVariable('base_script', BASE_SCRIPT);
        $tpl->SetVariable('lbl_actions', _t('GLOBAL_ACTIONS'));
        $tpl->SetVariable('lbl_no_action', _t('GLOBAL_NO_ACTION'));
        $tpl->SetVariable('lbl_delete', _t('GLOBAL_DELETE'));
        $tpl->SetVariable('icon_ok', STOCK_OK);
        $tpl->SetVariable('gid', $gid);
        $tpl->SetVariable('lbl_edit_group', _t('USERS_EDIT_GROUP'));
        $tpl->SetVariable('url_edit_group', $this->gadget->urlMap('EditUserGroup', array('gid' => $gid)));

        $members = $jUser->GetUsers($gid);
        $tpl->SetVariable('lbl_members', _t('USERS_GROUPS_MEMBERS'));
        $tpl->SetVariable('lbl_username', _t('USERS_USERS_USERNAME'));
        $tpl->SetVariable('lbl_nickname', _t('USERS_USERS_NICKNAME'));
        foreach($members as $member) {
            $tpl->SetBlock('manage_group/member');
            $tpl->SetVariable('id', $member['id']);
            $tpl->SetVariable('username', $member['username']);
            $tpl->SetVariable('nickname', $member['nickname']);

            // user's profile
            $tpl->SetVariable(
                'user_url',
                $this->gadget->urlMap('Profile', array('user' => $member['username']))
            );
            $tpl->ParseBlock('manage_group/member');
        }

        $allUsers = $jUser->GetUsers();
        if (count($allUsers) != count($members)) {
            $tpl->SetBlock('manage_group/all_users');
            $tpl->SetVariable('lbl_group_member', _t('USERS_MANAGE_GROUPS_MEMBERS'));
            $tpl->SetVariable('lbl_users', _t('USERS_USERS'));
            $tpl->SetVariable('lbl_add_user_to_group', _t('USERS_GROUPS_ADD_USER'));
            foreach ($allUsers as $user) {
                if (in_array($user, $members)) {
                    continue;
                }
                $tpl->SetBlock('manage_group/all_users/user');
                $tpl->SetVariable('user', $user['id']);
                $tpl->SetVariable('username', $user['username']);
                $tpl->SetVariable('nickname', $user['nickname']);
                $tpl->ParseBlock('manage_group/all_users/user');
            }
            $tpl->ParseBlock('manage_group/all_users');
        }

        $tpl->ParseBlock('manage_group');
        return $tpl->Get();
    }


    /**
     * Update a user's group
     *
     * @access  public
     * @return  void
     */
    function UpdateGroup()
    {
        $this->gadget->CheckPermission('ManageUserGroups');

        $post = jaws()->request->fetch(array('gid', 'name', 'title', 'description', 'enabled'), 'post');
        $selected_members = jaws()->request->fetch('members:array', 'post');
        $user = $GLOBALS['app']->Session->GetAttribute('user');
        $post['enabled'] = (bool) $post['enabled'];

        $jUser = new Jaws_User;
        $res = $jUser->UpdateGroup($post['gid'], $post, $user);

        $current_members_info = $jUser->GetUsers($post['gid']);
        $current_members = array();
        foreach($current_members_info as $member_info) {
            $current_members[] = $member_info['id'];
        }
        $new_member = array_diff($selected_members, $current_members);
        if (!Jaws_Error::isError($res) && count($new_member) > 0) {
            // TODO: improve performance
            foreach ($new_member as $member) {
                $res = $jUser->AddUserToGroup($member, $post['gid'], $user);
            }
        }

        $removed_member = array_diff($current_members, $selected_members);
        if (!Jaws_Error::isError($res) && count($removed_member) > 0) {
            // TODO: improve performance
            foreach ($removed_member as $member) {
                $res = $jUser->DeleteUserFromGroup($member, $post['gid'], $user);
            }
        }

        if (Jaws_Error::isError($res)) {
            $GLOBALS['app']->Session->PushResponse(
                $res->getMessage(),
                'Users.Groups',
                RESPONSE_ERROR
            );
        } elseif ($res == true) {
            $GLOBALS['app']->Session->PushResponse(
                _t('USERS_GROUPS_UPDATED', $post['title']),
                'Users.Groups',
                RESPONSE_NOTICE
            );
        }
        Jaws_Header::Location($this->gadget->urlMap('Groups'));
    }

}