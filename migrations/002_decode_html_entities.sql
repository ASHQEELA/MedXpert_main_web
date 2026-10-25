-- Run once on an existing database.
-- Older versions stored text with HTML entities (for example &amp; and &#039;).
-- The app now stores plain text and escapes it when printing, so this converts
-- the old rows back to plain text. Do not run it twice on data you edited after upgrading.
-- Take a backup first (phpMyAdmin > Export).

UPDATE users SET
  full_name = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(full_name,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  phone     = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  address   = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(address,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&');

UPDATE doctors SET
  name           = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  specialization = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(specialization,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  qualification  = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(qualification,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  hospital       = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(hospital,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  location       = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(location,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  bio            = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(bio,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&');

UPDATE blood_donors SET
  name               = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  location           = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(location,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  medical_conditions = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(medical_conditions,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&');

UPDATE medicines SET
  name         = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  category     = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(category,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  description  = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(description,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  manufacturer = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(manufacturer,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  dosage       = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(dosage,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&');

UPDATE appointments SET
  symptoms = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(symptoms,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&');

UPDATE medicine_orders SET
  delivery_address = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(delivery_address,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  phone            = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&');

UPDATE ambulance_requests SET
  name              = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  phone             = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  pickup_location   = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(pickup_location,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  destination       = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(destination,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  emergency_type    = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(emergency_type,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&'),
  patient_condition = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(patient_condition,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>'),'&amp;','&');
