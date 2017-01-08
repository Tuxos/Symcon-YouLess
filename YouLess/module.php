<?

	class YouLessLS110 extends IPSModule {

	public function Create() {

		parent::Create();

		$this->RegisterPropertyString("ipadress", "");
		$this->RegisterPropertyInteger("intervall", "20");

	}

	public function ApplyChanges() {

		parent::ApplyChanges();

		$this->RegisterProfile(1,"Watt.YouLess", "Plug", "", " Watt", 0, 8000, 1);
		$this->RegisterProfile(2,"kWatt.Youless", "Electricity", "", " kWh", 0, 0, 1);

		$this->RegisterVariableInteger("currentpower", "Aktuelle Leistung", "Watt.YouLess",1);
		$this->RegisterVariableInteger("signalstrength", "Signalstärke", "~Intensity.100",2);
		$id = $this->RegisterVariableFloat("actualmonth", "Verbrauch aktueller Monat", "kWatt.Youless",3);

		IPS_SetHidden($this->RegisterVariableFloat("meterbeginningofmonth", "Zählerstand anfang des Monats", "kWatt.Youless",4), true);
		$this->RegisterVariableFloat("yesterday", "Verbrauch gestern", "kWatt.Youless",5);
		$this->RegisterVariableFloat("today", "Verbrauch heute", "kWatt.Youless",6);
		IPS_SetHidden($this->RegisterVariableFloat("meteryesterday", "Zählerstand gestern", "kWatt.Youless",7), true);
		$this->RegisterVariableFloat("meterlastmonth", "Verbrauch letzter Monat", "kWatt.Youless",8);
		$this->RegisterVariableFloat("meterreading", "Zählerstand", "kWatt.Youless",9);

		$this->RegisterTimer('ReadData', $this->ReadPropertyInteger("intervall"), 'YL_readdata($id)');

		if (($this->ReadPropertyString("ipadress") != "") and (YL_readdata($this->InstanceID)->cnt != ""))
			{
				$this->SetStatus(102);
			} else {
				$this->SetStatus(202);
			}

	}

	// Erstelle Timer Events
	protected function RegisterTimer($ident, $interval, $script) {

		$id = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);

		if ($id && IPS_GetEvent($id)['EventType'] <> 1) {
			IPS_DeleteEvent($id);
			$id = 0;
		}

		if (!$id) {
			$id = IPS_CreateEvent(1);
			IPS_SetParent($id, $this->InstanceID);
			IPS_SetIdent($id, $ident);
		}

		IPS_SetName($id, $ident);
		IPS_SetHidden($id, true);
		IPS_SetEventScript($id, "\$id = \$_IPS['TARGET'];\n$script;");
		if (!IPS_EventExists($id)) throw new Exception("Ident with name $ident is used for wrong object type");

		if (!($interval > 0)) {
			IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, 1);
			IPS_SetEventActive($id, false);
		} else {
			IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, $interval);
			IPS_SetEventActive($id, true);
		}
	}

	// Erstelle Variablen Profil. $Type: 0=Boolean, 1=Integer, 2=Float, 3=String
	protected function RegisterProfile($Type, $Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize) {

		if(!IPS_VariableProfileExists($Name)) {
			IPS_CreateVariableProfile($Name, $Type);
			} else {
			$profile = IPS_GetVariableProfile($Name);
			if($profile['ProfileType'] != $Type)
			throw new Exception("Variable profile type does not match for profile ".$Name);
		}

		IPS_SetVariableProfileIcon($Name, $Icon);
		IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
		IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
		if ($Type == 2) IPS_SetVariableProfileDigits($Name, 3);

    }


	// Lese alle Konfigurationsdaten aus und schreibe sie in Variablen
	public function readdata() {

		// Lese & schreibe aktuelle Verbrauchsdaten
		$ip = $this->ReadPropertyString("ipadress");
		$url = "http://".$ip."/a?f=j";

		$data = json_decode(file_get_contents($url));

		SetValue(IPS_GetObjectIDByName("Aktuelle Leistung", $this->InstanceID), $data->pwr);
		SetValue(IPS_GetObjectIDByName("Signalstärke", $this->InstanceID), $data->lvl);
		SetValue(IPS_GetObjectIDByName("Zählerstand", $this->InstanceID), $data->cnt);

		// Lese, berechne und schreibe historische Verbrauchsdaten (wenn vorhanden)
		$month = date("n") - 1;
		$url = "http://".$ip."/V?m=".$month."?f=j";
		$data = json_decode(file_get_contents($url));

		$i = 0;
		$meterlastmonth = 0;
		while ($data->val[$i] != "null") {
			$i = $i + 1;
			$meterlastmonth = $meterlastmonth + $data->val[$i];
		}
		echo $meterlastmonth;
		print_r($data->val);

		}

	}
?>
