<?php
include 'connections.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function getSisData($sisConn) {
    $sql = "SELECT bi.FirstName, bi.MiddleName, bi.Surname, bi.ID as studentNumber,
            IF(bi.Sex = 'Male', 'M', 'F') AS Sex, bi.DateOfBirth, bi.Nationality,
            bi.StreetName, bi.Town, bi.Country, bi.HomePhone, bi.MobilePhone, bi.PrivateEmail,
            bi.MaritalStatus, s.ParentID, s.ShortName, p.PeriodEndDate, ce.EnrolmentDate, mu.password,
            bi.PostalCode 
            FROM `basic-information` bi 
            LEFT JOIN `course-electives` ce ON ce.StudentID = bi.ID 
            LEFT JOIN `student-study-link` ssl2 ON ssl2.StudentID = bi.ID 
            LEFT JOIN study s ON s.ID = ssl2.StudyID 
            LEFT JOIN `student-data-other` sdo ON sdo.StudentID = ssl2.StudentID 
            LEFT JOIN periods p ON p.ID = ce.PeriodID
            LEFT JOIN moodle_users mu ON mu.id = bi.ID 
            WHERE ce.PeriodID = 65
            GROUP BY bi.ID LIMIT 1";

    $result = mysqli_query($sisConn, $sql);
    
    if (!$result) {
        die("Query failed: " . mysqli_error($sisConn));
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    mysqli_free_result($result);
    
    return $data;
}

function checkKohaID($connection, $cardNumber) {
    $sql = "SELECT COUNT(*) AS count FROM koha_palabana_library.borrowers WHERE cardnumber = '$cardNumber'";

    $result = mysqli_query($connection, $sql);

    if (!$result) {
        die("Query failed: " . mysqli_error($connection));
    }

    $row = $result->fetch_assoc();

    return $row['count'] > 0;
}

function insertIntoKoha($sisConn, $kohaConn) {
    $sisData = getSisData($sisConn);
    foreach ($sisData as $data) {
        $cardNumber = $data['studentNumber'];

        if (checkKohaID($kohaConn, $cardNumber)) {
            echo "ID $cardNumber already exists in Koha. Skipping insertion.<br>";
            continue;
        }

        // Hash the password using bcrypt
        // $password = $data['studentNumber'];
        // $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $defaultPassword = "\$2a\$08\$wDNvlD10AzzvZXjVADUGJuGFBhshiorW.mug6.3IRNt./hZMew0OS";


        $sql = "INSERT INTO koha_palabana_library.borrowers
                (borrowernumber, cardnumber, surname, firstname, middle_name, title, othernames, initials,
                 pronouns, streetnumber, streettype, address, address2, city, state, zipcode, country, 
                 email, phone, mobile, fax, emailpro, phonepro, B_streetnumber, B_streettype, 
                 B_address, B_address2, B_city, B_state, B_zipcode, B_country, B_email, B_phone, 
                 dateofbirth, branchcode, categorycode, dateenrolled, dateexpiry, password_expiration_date, 
                 date_renewed, gonenoaddress, lost, debarred, debarredcomment, contactname, contactfirstname,
                 contacttitle, borrowernotes, relationship, sex, password, secret, auth_method, flags, 
                 userid, opacnote, contactnote, sort1, sort2, altcontactfirstname, altcontactsurname,
                 altcontactaddress1, altcontactaddress2, altcontactaddress3, altcontactstate, altcontactzipcode,
                 altcontactcountry, altcontactphone, smsalertnumber, sms_provider_id, privacy, 
                 privacy_guarantor_fines, privacy_guarantor_checkouts, checkprevcheckout, updated_on, lastseen,
                 lang, login_attempts, overdrive_auth_token, anonymized, autorenew_checkouts, primary_contact_method, protected)
                VALUES
                (NULL, '{$data['studentNumber']}', '{$data['Surname']}', '{$data['FirstName']}', '{$data['MiddleName']}', NULL, NULL, '{$data['ShortName']}',
                NULL, '{$data['StreetName']}', '{$data['StreetName']}', '{$data['StreetName']}', NULL, '{$data['Town']}', '{$data['Town']}', 
                '{$data['PostalCode']}', '{$data['Nationality']}', '{$data['PrivateEmail']}', '{$data['MobilePhone']}', '{$data['HomePhone']}', NULL, NULL, NULL,
                NULL,NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{$data['DateOfBirth']}', 'CPL', 'ST', '{$data['EnrolmentDate']}', '{$data['PeriodEndDate']}', NOW(),
                NULL, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '{$data['MaritalStatus']}', '{$data['Sex']}', '{$defaultPassword}', NULL, 'password', NULL, '{$data['studentNumber']}',
                'Welcome to Palabana Library. Books should be returned on time or before the due date to avoid overdue penalties.', NULL, '{$data['ParentID']}', '{$data['ShortName']}', 
                NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 'inherit', NULL, NULL, 'default', 0, NULL, 0, 1, NULL, 0)";

        // var_dump($sql);die();
        $result = mysqli_query($kohaConn, $sql);
        
        if (!$result) {
            echo "Failed to insert ID $cardNumber: " . mysqli_error($kohaConn) . "<br>";
        } else {
            echo "Successfully inserted ID $cardNumber into Koha.<br>";
        }
    }
}

insertIntoKoha($sisConn, $kohaConn);

mysqli_close($sisConn);
mysqli_close($kohaConn);

?>
