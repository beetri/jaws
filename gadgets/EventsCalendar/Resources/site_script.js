/**
 * EventsCalendar Javascript actions
 *
 * @category    Ajax
 * @package     EventsCalendar
 * @author      Mohsen Khahani <mkhahani@gmail.com>
 * @copyright   2013 Jaws Development Group
 * @license     http://www.gnu.org/copyleft/gpl.html
 */
/**
 * Use async mode, create Callback
 */
var ECCallback = {
    DeleteEvent: function(response) {
        if (response.type === 'response_error') {
            ECAjax.showResponse(response);
        } else {
            location.assign(events_url);
        }
    },

    UpdateShare: function(response) {
        if (response.type === 'response_error') {
            ECAjax.showResponse(response);
        } else {
            location.assign(events_url);
        }
    }
};

/**
 * Initiates Events
 */
function initEvents()
{
    ECAjax.backwardSupport();
}

/**
 * Shows/Hides event repeat options
 */
function toggleRepeat(checked)
{
    $('tbl_repeat').style.display = checked? 'table' : 'none';
}

/**
 * Selects/Deselects all rows
 */
function checkAll()
{
    var checked = $('chk_all').checked;
    $('grid_events').getElements('input').set('checked', checked);
}

/**
 * Submits search
 */
/*function searchEvents(form)
{
    if (form.query.value.length < 2) {
        alert(errorShortQuery);
        return;
    }
    form.submit();
}*/

/**
 * Shows/Hides search reset button
 */
function onSearchChange(value)
{
    $('btn_event_search_reset').style.display = (value === '')? 'none' : 'inline';
}

/**
 * Submits event
 */
function submitEvent(form)
{
    if (form.subject.value === '') {
        alert(errorIncompleteData);
        form.subject.focus();
        return;
    }
    if (form.start_time.value === '') {
        alert(errorIncompleteData);
        form.start_time.focus();
        return;
    }
    form.submit();
}

/**
 * Deletes current event
 */
function deleteEvent(id)
{
    if (confirm(confirmDelete)) {
        ECAjax.callAsync('DeleteEvent', {id_set:id});
    }
}

/**
 * Deletes selected events
 */
function deleteEvents()
{
    var id_set = $('grid_events').getElements('input:checked').get('value');
    if (id_set.length === 0) {
        return;
    }
    if (confirm(confirmDelete)) {
        ECAjax.callAsync('DeleteEvent', {id_set:id_set.join(',')});
    }
}

/**
 * Initiates Sharing
 */
function initShare()
{
    ECAjax.backwardSupport();
    $('sys_groups').selectedIndex = -1;
    Array.each($('event_users').options, function(opt) {
        sharedEventUsers[opt.value] = opt.text;
    });
}

/**
 * Fetches and displays users of selected group
 */
function toggleUsers(gid)
{
    var container = $('sys_users').empty(),
        users = usersByGroup[gid];
    if (users === undefined) {
        users = ECAjax.callSync('GetUsers', {'gid':gid});
        usersByGroup[gid] = users;
    }
    users.each(function (user) {
        if (user.id == UID) return;
        var div = new Element('div'),
            input = new Element('input', {type:'checkbox', id:'chk_'+user.id, value:user.id}),
            label = new Element('label', {'for':'chk_'+user.id});
        input.set('checked', (sharedEventUsers[user.id] !== undefined));
        input.addEvent('click', selectUser);
        label.set('html', user.nickname + ' (' + user.username + ')');
        div.adopt(input, label);
        container.grab(div);
    });
}

/**
 * Adds/removes user to/from shares
 */
function selectUser()
{
    if (this.checked) {
        sharedEventUsers[this.value] = this.getNext('label').get('html');
    } else {
        delete sharedEventUsers[this.value];
    }
    updateShareUsers();
}

/**
 * Updates list of event users
 */
function updateShareUsers()
{
    var list = $('event_users').empty();
    Object.each(sharedEventUsers, function(name, id) {
        list.options[list.options.length] = new Option(name, id);
    });
}

/**
 * Submits share data
 */
function submitShare(id)
{
    ECAjax.callAsync(
        'UpdateShare',
        {'id':id, 'users':Object.keys(sharedEventUsers).join(',')}
    );
}

var ECAjax = new JawsAjax('EventsCalendar', ECCallback),
    usersByGroup = {},
    sharedEventUsers = {};
