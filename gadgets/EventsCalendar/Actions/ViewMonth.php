<?php
/**
 * EventsCalendar Gadget
 *
 * @category    Gadget
 * @package     EventsCalendar
 * @author      Mohsen Khahani <mkhahani@gmail.com>
 * @copyright   2013-2014 Jaws Development Group
 * @license     http://www.gnu.org/copyleft/gpl.html
 */
$GLOBALS['app']->Layout->AddHeadLink('gadgets/EventsCalendar/Resources/site_style.css');
class EventsCalendar_Actions_ViewMonth extends Jaws_Gadget_Action
{
    /**
     * Builds month view UI
     *
     * @access  public
     * @return  string  XHTML UI
     */
    function ViewMonth()
    {
        $data = jaws()->request->fetch(array('year', 'month'), 'get');
        $year = (int)$data['year'];
        $month = (int)$data['month'];

        $this->AjaxMe('site_script.js');
        $tpl = $this->gadget->template->load('ViewMonth.html');
        $tpl->SetBlock('month');

        // Menubar
        $action = $this->gadget->action->load('Menubar');
        $tpl->SetVariable('menubar', $action->Menubar('Events'));

        $tpl->SetVariable('lbl_day', _t('EVENTSCALENDAR_DAY'));
        $tpl->SetVariable('lbl_events', _t('EVENTSCALENDAR_EVENTS'));

        $jdate = Jaws_Date::getInstance();
        $daysInMonth = $jdate->monthDays($year, $month);
        $start = $jdate->ToBaseDate($year, $month, 1);
        $start = $GLOBALS['app']->UserTime2UTC($start['timestamp']);
        $stop = $jdate->ToBaseDate($year, $month, $daysInMonth, 23, 59, 59);
        $stop = $GLOBALS['app']->UserTime2UTC($stop['timestamp']);

        // Current month
        $info = $jdate->GetDateInfo($year, $month, 1);
        $tpl->SetVariable('year', $info['year']);
        $tpl->SetVariable('month', $info['month']);
        $tpl->SetVariable('year_url',
            $this->gadget->urlMap('ViewYear', array('year' => $info['year'])));

        $current = $jdate->Format($start, 'Y MN');
        $tpl->SetVariable('title', $current);
        $this->SetTitle($current . ' - ' . _t('EVENTSCALENDAR_EVENTS'));

        // Previous month
        $prev = $jdate->ToBaseDate($year, $month - 1, 1);
        $prev = $jdate->Format($prev['timestamp'], 'Y MN');
        $tpl->SetVariable('prev', $prev);
        $prevYear = $year;
        $prevMonth = $month - 1;
        if ($prevMonth === 0) {
            $prevMonth = 12;
            $prevYear--;
        }
        $prevURL = $this->gadget->urlMap('ViewMonth', array(
            'year' => $prevYear,
            'month' => $prevMonth
        ));
        $tpl->SetVariable('prev_url', $prevURL);

        // Next month
        $next = $jdate->ToBaseDate($year, $month + 1, 1);
        $next = $jdate->Format($next['timestamp'], 'Y MN');
        $tpl->SetVariable('next', $next);
        $nextYear = $year;
        $nextMonth = $month + 1;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }
        $nextURL = $this->gadget->urlMap('ViewMonth', array(
            'year' => $nextYear,
            'month' => $nextMonth
        ));
        $tpl->SetVariable('next_url', $nextURL);

        // Fetch events
        $model = $this->gadget->model->load('Calendar');
        $user = (int)$GLOBALS['app']->Session->GetAttribute('user');
        $events = $model->GetEvents($user, null, null, $start, $stop);
        if (Jaws_Error::IsError($events)){
            $events = array();
        }

        // Prepare events
        $eventsById = array();
        $eventsByDay = array_fill(1, $daysInMonth, array());
        foreach ($events as $e) {
            $eventsById[$e['id']] = $e;
            $startIdx = ($e['start_time'] <= $start)? 1:
                floor(($e['start_time'] - $start) / 86400) + 1;
            $stopIdx = ($e['stop_time'] >= $stop)? $daysInMonth:
                ceil(($e['stop_time'] - $start) / 86400);
            for ($i = $startIdx; $i <= $stopIdx; $i++) {
                if (!in_array($e['id'], $eventsByDay[$i])) {
                    $eventsByDay[$i][] = $e['id'];
                }
            }
        }

        // Display events
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $date = $jdate->ToBaseDate($year, $month, $i);
            $weekDay = $jdate->Format($date['timestamp'], 'DN');
            $tpl->SetBlock('month/day');
            $day_url = $this->gadget->urlMap('ViewDay', array(
                'year' => $year,
                'month' => $month,
                'day' => $i
            ));
            $tpl->SetVariable('day_url', $day_url);
            $tpl->SetVariable('day', $i . ' ' . $weekDay);
            foreach ($eventsByDay[$i] as $event_id) {
                $e = $eventsById[$event_id];
                $tpl->SetBlock('month/day/event');

                $tpl->SetVariable('event', $e['subject']);
                $tpl->SetVariable('type', $e['type']);

                if ($e['priority'] > 0) {
                    $tpl->SetVariable('priority', ($e['priority'] == 1)? 'low' : 'high');
                } else {
                    $tpl->SetVariable('priority', '');
                }

                $url = $this->gadget->urlMap('ViewEvent', array('id' => $event_id));
                $tpl->SetVariable('event_url', $url);

                if ($e['shared']) {
                    $block = ($e['user'] == $e['owner'])? 'shared' : 'foreign';
                    $tpl->SetBlock("month/day/event/$block");
                    $tpl->ParseBlock("month/day/event/$block");
                }
                $tpl->ParseBlock('month/day/event');
            }
            $tpl->ParseBlock('month/day');
        }

        $tpl->ParseBlock('month');
        return $tpl->Get();
    }
}