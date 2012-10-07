<?php
/**
 * GD implementation for Jaws_Image
 *
 * @category   Image
 * @package    Core
 * @author     Ali Fazelzadeh <afz@php.net>
 * @copyright  2012 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 * @link       http://pear.php.net/package/Image_Transform
 */
class Jaws_Image_GD extends Jaws_Image
{

    /**
     * Class constructor
     */
    function Jaws_Image_GD()
    {
        if (!extension_loaded('gd')) {
            return Jaws_Error::raiseError('GD library is not available.',
                                          __FUNCTION__);
        } else {
            $types = imagetypes();
            if ($types & IMG_PNG) {
                $this->_supported_image_types['png'] = 'rw';
            }
            if (($types & IMG_GIF) ||
                function_exists('imagegif') ||
                function_exists('imagecreatefromgif'))
            {
                $this->_supported_image_types['gif'] = 'rw';
            }
            if ($types & IMG_JPG) {
                $this->_supported_image_types['jpeg'] = 'rw';
            }
            if ($types & IMG_WBMP) {
                $this->_supported_image_types['wbmp'] = 'rw';
            }
            if ($types & IMG_XPM) {
                $this->_supported_image_types['xpm'] = 'r';
            }
            if (empty($this->_supported_image_types)) {
                return Jaws_Error::raiseError('No supported image types available.',
                                              __FUNCTION__);
            }
        }
    }

    /**
     * Returns a new image for temporary processing
     *
     * @param int $width width of the new image
     * @param int $height height of the new image
     * @param bool $trueColor force which type of image to create
     * @return resource a GD image resource
     * @access protected
     */
    function _createImage($width = -1, $height = -1, $trueColor = null)
    {
        if ($width == -1) {
            $width = $this->_img_w;
        }
        if ($height == -1) {
            $height = $this->_img_h;
        }

        $new_img = null;
        if (is_null($trueColor)) {
            if (function_exists('imageistruecolor')) {
                $createtruecolor = imageistruecolor($this->_hImage);
            } else {
                $createtruecolor = true;
            }
        } else {
            $createtruecolor = $trueColor;
        }

        if ($createtruecolor && function_exists('imagecreatetruecolor')) {
            $new_img = @imagecreatetruecolor($width, $height);
        }

        if (!$new_img) {
            $new_img = imagecreate($width, $height);
            imagepalettecopy($new_img, $this->_hImage);
        }

        if ($this->_itype != 'gif') {
            imagealphablending($new_img, false);
            imagesavealpha($new_img, true);
        }

        $color = imagecolortransparent($this->_hImage);
        if ($color != -1) {
            imagecolortransparent($new_img, $color);
            imagefill($new_img, 0, 0, $color);
        }

        return $new_img;
    }

    /**
     * Loads an image from file
     *
     * @param string    $image filename
     * @param boolean   $readonly readonly
     * @return mixed True or a Jaws_Error object on error
     * @access public
     */
    function load($filename, $readonly = false)
    {
        $result = parent::load($filename, $readonly);
        if (Jaws_Error::IsError($result)) {
            return $result;
        }

        if (!$readonly) {
            $funcName = 'imagecreatefrom' . $this->_itype;
            $this->_hImage = $funcName($filename);
            if (!$this->_hImage) {
                $this->_hImage = null;
                return Jaws_Error::raiseError('Error while loading image file.',
                                              __FUNCTION__);
            }
        }

        return true;
    }

    /**
     * Loads an image from raw data
     *
     * @param string    $data  image raw data
     * @param boolean   $readonly readonly
     * @return mixed True or a Jaws_Error object on error
     * @access public
     */
    function setData($data, $readonly = false)
    {
        $result = parent::setData($data, $readonly);
        if (Jaws_Error::IsError($result)) {
            return $result;
        }

        if (!$readonly) {
            $this->_hImage = imagecreatefromstring($data);
            if (!$this->_hImage) {
                $this->_hImage = null;
                return Jaws_Error::raiseError('Error while loading image from string.',
                                              __FUNCTION__);
            }
        }

        return true;
    }

    /**
     * Returns the GD image handle
     *
     * @return resource
     * @access public
     */
    function &getHandle()
    {
        return $this->_hImage;
    }

    /**
     * Resizes the image
     *
     * For GD 2.01+ the new copyresampled function is used
     * It uses a bicubic interpolation algorithm to get far
     * better result.
     *
     * @param int   $new_w   New width
     * @param int   $new_h   New height
     * @param array $options Optional parameters(eg. 'scaleMethod': "pixel" or "smooth")
     * @return mixed True on success or Jaws_Error object on error
     * @access public
     */
    function resize($new_w, $new_h, $options = null)
    {
        $this->_parse_size_by_aspect_ratio($new_w, $new_h);
        $scaleMethod = $this->_getOption('scaleMethod', $options, 'smooth');
        $trueColor = ($scaleMethod == 'pixel') ? null : true;
        $new_img = $this->_createImage($new_w, $new_h, $trueColor);

        $icr_res = null;
        if ($scaleMethod != 'pixel' && function_exists('imagecopyresampled')) {
            $icr_res = imagecopyresampled($new_img, $this->_hImage, 0, 0, 0, 0,
                                          $new_w, $new_h, $this->_img_w, $this->_img_h);
        }

        if (!$icr_res) {
            imagecopyresized($new_img, $this->_hImage, 0, 0, 0, 0,
                             $new_w, $new_h, $this->img_x, $this->img_y);
        }

        $this->_hImage = $new_img;
        $this->_img_w  = $new_w;
        $this->_img_h  = $new_h;
        return true;
    }

    /**
     * Crops image by size and start coordinates
     *
     * @param int width Cropped image width
     * @param int height Cropped image height
     * @param int x X-coordinate to crop at
     * @param int y Y-coordinate to crop at
     * @return mixed True or a Jaws_Error object on error
     * @access public
     */
    function crop($width, $height, $x = 0, $y = 0)
    {
        // Sanity check
        if (!$this->_intersects($width, $height, $x, $y)) {
            return Jaws_Error::raiseError('Nothing to crop.',
                                          __FUNCTION__);
        }

        $x = min($this->_img_w, max(0, $x));
        $y = min($this->_img_h, max(0, $y));
        $width   = min($width,  $this->_img_w - $x);
        $height  = min($height, $this->_img_h - $y);
        $new_img = $this->_createImage($width, $height);

        if (!imagecopy($new_img, $this->_hImage, 0, 0, $x, $y, $width, $height)) {
            imagedestroy($new_img);
            return Jaws_Error::raiseError('Failed transformation: crop().',
                                          __FUNCTION__);
        }

        imagedestroy($this->_hImage);
        $this->_hImage = $new_img;
        $this->_img_w  = $width;
        $this->_img_h  = $height;
        return true;
    }

    /**
     * Rotates image by the given angle
     * Uses a fast rotation algorythm for custom angles or lines copy for multiple of 90 degrees
     *
     * @param int   $angle   Rotation angle
     * @param array $options array('canvasColor' => array(r ,g, b), named color or #rrggbb)
     * @author Pierre-Alain Joye
     * @return bool True on success or a false on error
     * @access public
     */
    function rotate($angle, $options = null)
    {
        if (($angle % 360) == 0) {
            return true;
        }

        $color_mask = $this->_getColor('canvasColor',
                                       $options,
                                       array(255, 255, 255));
        $mask = imagecolorresolve($this->_hImage,
                                  $color_mask[0],
                                  $color_mask[1],
                                  $color_mask[2]);

        // Multiply by -1 to change the sign, so the image is rotated clockwise
        $this->_hImage = imagerotate($this->_hImage, $angle * -1, $mask);
        if (false === $this->_hImage) {
            return Jaws_Error::raiseError('Failed transformation: rotate().',
                                          __FUNCTION__);
        }

        $this->_img_w = imagesx($this->_hImage);
        $this->_img_h = imagesy($this->_hImage);
        $new_img = $this->_createImage();
        if (!imagecopy($new_img, $this->_hImage, 0, 0, 0, 0, $this->_img_w, $this->_img_h)) {
            imagedestroy($new_img);
            return Jaws_Error::raiseError('Failed transformation: rotate().',
                                          __FUNCTION__);
        }

        imagedestroy($this->_hImage);
        $this->_hImage = $new_img;
        return true;
    }

    /**
     * Adjusts the image gamma
     *
     * @param float $gamma
     *
     * @return mixed True or a Jaws_Error on error
     * @access public
     **/
    function gamma($gamma = 1.0)
    {
        $res = imagegammacorrect($this->_hImage, 1.0, $gamma);
        if (false === $res) {
            return Jaws_Error::raiseError('Failed transformation: gamma().',
                                          __FUNCTION__);
        }

        return true;
    }

    /**
     * Horizontal mirroring
     *
     * @return mixed True or Jaws_Error on error
     * @access public
     **/
    function mirror()
    {
        $new_img = $this->_createImage();
        for ($x = 0; $x < $this->_img_w; ++$x) {
            imagecopy($new_img, $this->_hImage, $x, 0, $this->_img_w - $x - 1, 0, 1, $this->_img_h);
        }

        imagedestroy($this->_hImage);
        $this->_hImage = $new_img;
        return true;
    }

    /**
     * Vertical mirroring
     *
     * @return mixed True or Jaws_Error on error
     * @access public
     **/
    function flip()
    {
        $new_img = $this->_createImage();
        for ($y = 0; $y < $this->_img_h; ++$y) {
            imagecopy($new_img, $this->_hImage, 0, $y, 0, $this->_img_h - $y - 1, $this->_img_w, 1);
        }

        imagedestroy($this->_hImage);
        $this->_hImage = $new_img;
        return true;
    }

    /**
     * Converts an image into grayscale colors
     *
     * @return mixed True or Jaws_Error on error
     **/
    function grayscale()
    {
        $res = imagecopymergegray($this->_hImage,
                                  $this->_hImage,
                                  0, 0, 0, 0,
                                  $this->_img_w, $this->_img_h,
                                  0);
        if (false === $res) {
            return Jaws_Error::raiseError('Failed transformation: grayscale().',
                                          __FUNCTION__);
        }

        return true;
    }

    /**
     * Saves the image to a file
     *
     * @param string $filename the name of the file to write to
     * @param string $type     the output format, default is the current used format
     * @param int    $quality  default is 75
     *
     * @return mixed True on success or Jaws_Error object on error
     * @access public
     */
    function save($filename = '', $type = '', $quality = null)
    {
        $options = (is_array($quality)) ? $quality : array();
        if (is_numeric($quality)) {
            $options['quality'] = $quality;
        }
        $quality = $this->_getOption('quality', $options, 75);
        $type = ($type == 'jpg')? 'jpeg' : $type;
        $type = strtolower(($type == '') ? $this->_itype : $type);

        if (!$this->_typeSupported($type, 'w')) {
            return Jaws_Error::raiseError('Image type not supported for output.',
                                          __FUNCTION__);
        }
        
        $funcName = 'image' . $type;
        $filename = empty($filename)? $this->_ifname : $filename;
        switch ($type) {
            case 'jpeg':
                $result = $funcName($this->_hImage, $filename, $quality);
                break;
            default:
                $result = $funcName($this->_hImage, $filename);
        }

        if (!$result) {
            return Jaws_Error::raiseError('Couldn\'t save image to file',
                                          __FUNCTION__);
        }

        return true;
    }

    /**
     * Displays image without saving and lose changes.
     * This method adds the Content-type HTTP header
     *
     * @param string $type     the output format, default is the current used format
     * @param int    $quality  default is 75
     * @param int    $expires  set Cache-Control and Expires of HTTP header
     *
     * @return mixed True on success or Jaws_Error object on error
     * @access public
     */
    function display($type = '', $quality = null, $expires = 0)
    {
        if ($this->_readonly) {
            $result = parent::display($type, $quality, $expires);
            return $result;
        }

        $options = (is_array($quality)) ? $quality : array();
        if (is_numeric($quality)) {
            $options['quality'] = $quality;
        }
        $quality = $this->_getOption('quality', $options, 75);
        $type = ($type == 'jpg')? 'jpeg' : $type;
        $type = strtolower(($type == '') ? $this->_itype : $type);
        $type = empty($type)? 'png' : $type;

        if (!$this->_typeSupported($type, 'w')) {
            return Jaws_Error::raiseError('Image type not supported for output.',
                                          __FUNCTION__);
        }

        if (!empty($expires)) {
            header("Cache-Control: max-age=". $expires);
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires). ' GMT');
        }

        if (function_exists('imagealphablending')) {
            imagealphablending($this->_hImage, false);
            imagesavealpha($this->_hImage, true);
        }

        header('Content-type: ' . image_type_to_mime_type($this->get_image_extension_to_type($type)));
        $funcName = 'image' . $type;
        switch ($type) {
            case 'jpeg':
                $result = $funcName($this->_hImage, null, $quality);
                break;
            default:
                $result = $funcName($this->_hImage);
        }

        if (!$result) {
            return Jaws_Error::raiseError('Couldn\'t display image',
                                          __FUNCTION__);
        }

        $this->free();
        return true;
    }

    /**
     * Destroys image handle
     *
     * @access public
     */
    function free()
    {
        if (is_resource($this->_hImage)) {
            imagedestroy($this->_hImage);
        }

        parent::free();
    }

}