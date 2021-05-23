<?php

// Klassendefinition
class IcingaHelper extends IPSModule {
	
	protected $colors;
	protected $serviceStates;
	
	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID);

		// Selbsterstellter Code
		$this->colors = Array();
		$this->colors['OK']     = '#00ff00';
		$this->colors['WARN']   = '#ffff00';
		$this->colors['CRIT']   = '#ff0000';
		$this->colors['UNKNOWN'] = '#ff00ff';
		
		$this->serviceStates = Array();
		$this->serviceStates[0] = 'OK';
		$this->serviceStates[1] = 'WARN';
		$this->serviceStates[2] = 'CRIT';
		$this->serviceStates[3] = 'UNKNOWN';
	}

	// Überschreibt die interne IPS_Create($id) Funktion
	public function Create() {
		
		// Diese Zeile nicht löschen.
		parent::Create();

		// Properties
		$this->RegisterPropertyString("Sender","IcingaHelper");
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyBoolean("DebugOutput",false);
		$this->RegisterPropertyInteger("IcingaInstance",0);
		$this->RegisterPropertyString("HostName","");
		$this->RegisterPropertyString("ServiceName","");
		$this->RegisterPropertyInteger("MaxCheckAge",300);
		
		// Variables
		$this->RegisterVariableBoolean("Status","Status","~Switch");
		$this->RegisterVariableBoolean("Result","Result","~Alert");
		$this->RegisterVariableString("ResultDetails","Result Details","~HTMLBox");
		
		//Actions
		$this->EnableAction("Status");

		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'ICINGAHELPER_RefreshInformation($_IPS[\'TARGET\']);');
    }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {

		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);
			
		// Diese Zeile nicht löschen
		parent::ApplyChanges();
	}


	public function GetConfigurationForm() {
        	
		// Initialize the form
		$form = Array(
            		"elements" => Array(),
					"actions" => Array()
        		);

		// Add the Elements
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "RefreshInterval", "caption" => "Refresh Interval");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "DebugOutput", "caption" => "Enable Debug Output");
		
		$form['elements'][] = Array("type" => "SelectInstance", "name" => "IcingaInstance", "caption" => "Icinga Instance");
		$form['elements'][] = Array("type" => "ValidationTextBox", "name" => "HostName", "caption" => "Host Name");
		$form['elements'][] = Array("type" => "ValidationTextBox", "name" => "ServiceName", "caption" => "Service Name");
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "MaxCheckAge", "caption" => "Maximum Check Age");
		
		// Add the buttons for the test center
		$form['actions'][] = Array(	"type" => "Button", "label" => "Refresh", "onClick" => 'ICINGAHELPER_RefreshInformation($id);');
		// Return the completed form
		return json_encode($form);

	}
	
	// Version 1.0
	protected function LogMessage($message, $severity = 'INFO') {
		
		$logMappings = Array();
		// $logMappings['DEBUG'] 	= 10206; Deactivated the normal debug, because it is not active
		$logMappings['DEBUG'] 	= 10201;
		$logMappings['INFO']	= 10201;
		$logMappings['NOTIFY']	= 10203;
		$logMappings['WARN'] 	= 10204;
		$logMappings['CRIT']	= 10205;
		
		if ( ($severity == 'DEBUG') && ($this->ReadPropertyBoolean('DebugOutput') == false )) {
			
			return;
		}
		
		$messageComplete = $severity . " - " . $message;
		parent::LogMessage($messageComplete, $logMappings[$severity]);
	}

	public function RefreshInformation() {

		$this->LogMessage("Refresh in Progress", "DEBUG");
		if ($this->GetIDForIdent("Status") ) {
		
			$this->Check();
		}
		else {
		
			$this->LogMessage("Fetching status from Icinga server is deactivated", "DEBUG");
		}
	}

	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
			case "Status":
				SetValue($this->GetIDForIdent($Ident), $Value);
				// Turn alarm off when checking is deactivated.
				if (! $Value) {
					
					SetValue($this->GetIDForIdent("Result"), true);
					SetValue($this->GetIDForIdent("ResultDetails"), "");
				}
				else {
					
					$this->Check();
				}
				break;
			default:
				throw new Exception("Invalid Ident");
		}
	}
	
	protected function Check() {
		
		$checkResultsJson = Icinga2_Query4Service($this->ReadPropertyInteger("IcingaInstance"),$this->ReadPropertyString("ServiceName"),$this->ReadPropertyString("HostName") );
		$checkResults = json_decode($checkResultsJson, true);
		
		$serviceDetails = $checkResults[0]['attrs'];
		$lastCheckTimestamp = time() - $this->ReadPropertyInteger("MaxCheckAge");
		
		if (floatval($serviceDetails['last_check']) < $lastCheckTimestamp ) {
		
			$this->UpdateResult("UNKNOWN","UNKNOWN - The last check is older than " . $this->ReadPropertyInteger("MaxCheckAge") . " seconds ago");
			return;
		}
		
		$serviceStateNum = $serviceDetails['state'];
		$serviceState = $this->serviceStates[$serviceStateNum];
		$lastCheckOutput = $serviceDetails['last_check_result']['output'];
	
		$this->UpdateResult($serviceState, $lastCheckOutput);
		return;
	}
	
	protected function UpdateResult($status, $text) {
		
		$htmlText = '<table>' .
						'<tr>' .
							'<th align="left">Host:&nbsp;</th>' .
							'<td>' . $this->ReadPropertyString("HostName") . '</td>' .
						'</tr>' .
						'<tr>' .
							'<th align="left">Service:&nbsp;</th>' .
							'<td>' . $this->ReadPropertyString("ServiceName") . '</td>' .
						'</tr>' .
						'<tr>' .
							'<th align="left">Status:&nbsp;</th>' .
							'<td bgcolor="' . $this->colors[$status] . '"><font color="#333333">' . $text . '</font></td>' .
						'</tr>' .
					'</table>';

		SetValue($this->GetIDForIdent("ResultDetails"), $htmlText);
		
		return;
	}
}
