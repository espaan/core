<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

namespace Zikula\Module\MailerModule;

/**
 * Version information for the mailer module
 */
class MailerModuleVersion extends \Zikula_AbstractVersion
{
    /**
     * Generate an array of meta data about this module
     *
     * @return array meta data array
     */
    public function getMetaData()
    {
        $meta = array();
        $meta['displayname']    = $this->__('Mailer Module');
        $meta['description']    = $this->__('Mailer module, provides mail API and mail setting administration.');
        //! module name that appears in URL
        $meta['url']            = $this->__('mailer');
        $meta['version']        = '1.4.1';
        $meta['core_min']       = '1.4.0';

        $meta['securityschema'] = array('ZikulaMailerModule::' => '::');

        return $meta;
    }
}
