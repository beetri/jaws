<?php
/**
 * Blocks Admin Gadget
 *
 * @category   GadgetAdmin
 * @package    Blocks
 * @author     Jonathan Hernandez <ion@suavizado.com>
 * @copyright  2004-2013 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/gpl.html
 */
class Blocks_AdminAction extends Jaws_Gadget_Action
{
    /**
     * Creates and prints the administration template
     *
     * @access  public
     * @return  string  XHTML Template content
     */
    function Admin()
    {
        $gadgetHTML = $this->gadget->loadAdminAction('Block');
        return $gadgetHTML->Block();

    }
}