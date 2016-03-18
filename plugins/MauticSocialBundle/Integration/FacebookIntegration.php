<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticSocialBundle\Integration;

use Mautic\LeadBundle\Entity\Lead;
/**
 * Class FacebookIntegration
 */
class FacebookIntegration extends SocialIntegration
{
    /**
     * Used in getUserData to prevent a double user search call with getUserId
     *
     * @var bool
     */
    private $preventDoubleCall = false;

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'Facebook';
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifierFields()
    {
        return array(
            'facebook'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedFeatures()
    {
        return array(
            'share_button',
            'login_button'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthenticationUrl()
    {
        return 'https://www.facebook.com/dialog/oauth';
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenUrl()
    {
        return 'https://graph.facebook.com/oauth/access_token';
    }

    /**
     * {@inheritdoc}
     *
     * @param string $data
     * @param bool   $postAuthorization
     *
     * @return mixed
     */
    public function parseCallbackResponse($data, $postAuthorization = false)
    {
        if ($postAuthorization) {
            parse_str($data, $values);

            return $values;
        } else {
            return parent::parseCallbackResponse($data, $postAuthorization);
        }
    }

    /**
     * @param $endpoint
     *
     * @return string
     */
    public function getApiUrl($endpoint)
    {
        return "https://graph.facebook.com/$endpoint";
    }

    /**
     * Get public data
     *
     * @param $identifier
     * @param $socialCache
     *
     * @return array
     */
    public function getUserData($identifier, &$socialCache)
    {
        //tell getUserId to return a user array if it obtains it
        $this->preventDoubleCall = true;

        if ($id = $this->getUserId($identifier, $socialCache)) {
            if (is_object($id)) {
                //getUserId has already obtained the data
                $data = $id;
            } else {
                $url    = $this->getApiUrl("$id");
                //@todo - can't use access token to do a global search; may not work after April
                $data   = $this->makeRequest($url, array(), 'GET', array('auth_type' => 'rest'));
            }

            if (is_object($data) && !isset($data->error)) {
                $info                  = $this->matchUpData($data);
                if (isset($data->username)) {
                    $info['profileHandle'] = $data->username;
                } elseif (isset($data->link)) {
                    $info['profileHandle'] = str_replace('https://www.facebook.com/', '', $data->link);
                } else {
                    $info['profileHandle'] = $data->id;
                }

                $info['profileImage']  = "https://graph.facebook.com/{$data->id}/picture?type=large";

                $socialCache['profile'] = $info;
            }
            $this->preventDoubleCall = false;
        }
    }

    /**$post
     * {@inheritdoc}
     */
    public function getUserId($identifier, &$socialCache)
    {
        if (!empty($socialCache['id'])) {
            return $socialCache['id'];
        } elseif (empty($identifier)) {
            return false;
        }

        $identifiers = $this->cleanIdentifier($identifier);

        if (isset($identifiers['facebook'])) {
            $url    = $this->getApiUrl($identifiers["facebook"]);
            //@todo - can't use access token to do a global search; may not work after April
            $data   = $this->makeRequest($url, array(), 'GET', array('auth_type' => 'rest'));

            if ($data && isset($data->id)) {
                $socialCache['id'] = $data->id;

                //return the entire data set if the function has been called from getUserData()
                return ($this->preventDoubleCall) ? $data : $socialCache['id'];
            }
        }

        return false;
    }

	/**
	 * Convert and assign the data to assignable lead fields
	 *
	 * @param $data
	 *
	 * @return array
	 */
	protected function matchUpData($data)
	{
		$info = array();
		$available = $this->getAvailableLeadFields();
		
		$socialToLeads = $this->getIntegrationFieldsToLeadFields();
		$socialProfileUrls = $this->factory->getHelper('integration')->getSocialProfileUrlRegex();

		foreach ($available as $field => $fieldDetails)
		{
			if (!isset($data->$field) || !isset($socialToLeads[$field]))
			{
				$info[$field] = '';
			}
			else
			{
				$values = $data->$field;

				switch ($fieldDetails['type'])
				{
					case 'string':
					case 'boolean':
						$key = $socialToLeads[$field];
						$info[$key] = $values;
						break;
					case 'object':
						foreach ($fieldDetails['fields'] as $f)
						{
							$name = (stripos($f, $field) === false) ? $f . ucfirst($field) : $f;
							if (isset($values->$f))
							{
								$key = $socialToLeads[$name];
								$info[$key] = $values->$f;
							}
						}
						break;
					case 'array_object':
						if ($field == "link")
						{
							$key = $socialToLeads[$field];
							$info[$key] = "facebook ({$values})";
						}

						break;
				}
			}
		}
		$this->factory->getLogger()->addError(print_r($info, true));
		return $info;
	}

    /**
     * {@inheritdoc}
     */
    public function getAvailableLeadFields($settings = array())
    {
        // Until lead profile support is restored
        //return array();

        return array(
            'first_name' => array('type' => 'string'),
            'last_name'  => array('type' => 'string'),
            'name'       => array('type' => 'string'),
            'gender'     => array('type' => 'string'),
            'locale'     => array('type' => 'string'),
            'email'      => array('type' => 'string'),
			'link'		 => array(
								"type" => "array_object",
								"fields" => array(
									"Profile"
									)
							),
        );
    }

	/**
	 * {@inheritdoc}
	 */
	public function getIntegrationFieldsToLeadFields($settings = array())
	{
		return array(
			'first_name' => 'firstname',
			'last_name' => 'lastname',
			'name' => 'name',			//not integrated
			'gender' => 'gender',			//not integrated
			'locale' => 'locale',			//not integrated
			'email' => 'email',
			'link'	=> 'facebook'
		);
	}

	/**
	 * create or update existing lead
	 *
	 * @socialdata $data
	 *
	 */
	public function createLead($data)
	{

		$leadModel = $this->factory->getModel('lead');
		$uniqueLeadFields = $this->factory->getModel('lead.field')->getUniqueIdentiferFields();
		$uniqueLeadFieldData = array();

		$leadValues = json_decode($data, true);
		$matchedFields = $this->matchUpData((object)$leadValues);

		foreach ($matchedFields as $leadField => $value)
		{
			if (array_key_exists($leadField, $uniqueLeadFields) && !empty($value))
			{
				$uniqueLeadFieldData[$leadField] = $value;
			}
		}

		// Default to new lead
		$lead = new Lead();
		$lead->setNewlyCreated(true);

		if (count($uniqueLeadFieldData))
		{
			$existingLeads = $this->factory->getEntityManager()->getRepository('MauticLeadBundle:Lead')->getLeadsByUniqueFields($uniqueLeadFieldData);

			if (!empty($existingLeads))
			{
				foreach ($existingLeads as $existingLead)
				{
					$leadModel->setFieldValues($existingLead, $matchedFields, false);
					$leadModel->saveEntity($existingLead);
				}
				$lead = $existingLeads[0];
			}
		}

		$leadModel->setFieldValues($lead, $matchedFields, false);

		$lead->setLastActive(new \DateTime());

		$leadModel->saveEntity($lead, false);

		$leadModel->setSystemCurrentLead($lead);
	}
}
