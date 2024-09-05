#!/usr/bin/php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

function getSisData($sisConn) {
    $sql = "SELECT bi.FirstName, bi.MiddleName, bi.Surname, bi.ID as studentNumber,
            IF(bi.Sex = 'Male', 'M', 'F') AS Sex, bi.DateOfBirth, bi.Nationality,
            IF(bi.Sex = 'Male','Mr','Miss') AS title,
            bi.StreetName, bi.Town, bi.Country, bi.HomePhone, bi.MobilePhone, bi.PrivateEmail,
            bi.MaritalStatus, s.ParentID, s.ShortName, p.PeriodEndDate, ce.EnrolmentDate,
            bi.PostalCode 
            FROM `basic-information` bi 
            LEFT JOIN `course-electives` ce ON ce.StudentID = bi.ID 
            LEFT JOIN `student-study-link` ssl2 ON ssl2.StudentID = bi.ID 
            LEFT JOIN study s ON s.ID = ssl2.StudyID 
            LEFT JOIN `student-data-other` sdo ON sdo.StudentID = ssl2.StudentID 
            LEFT JOIN periods p ON p.ID = ce.PeriodID
            WHERE p.Intake = 1 and ce.studentLevel = 1
            GROUP BY bi.ID LIMIT 7";

    $result = mysqli_query($sisConn, $sql);
    
    if (!$result) {
        die("Query failed: " . mysqli_error($sisConn));
    }
 

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    mysqli_free_result($result);
    
    // echo "<pre>";
    // print_r($data);
    // echo "</pre>";
    // die();

    return $data;
}

function checkKohaID($connection, $cardNumber) {
    $sql = "SELECT COUNT(*) AS count FROM koha_library.borrowers WHERE cardnumber = '$cardNumber'";

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

        $surname = mysqli_real_escape_string($kohaConn, $data['Surname']);
        $firstName = mysqli_real_escape_string($kohaConn, $data['FirstName']);
        $middleName = mysqli_real_escape_string($kohaConn, $data['MiddleName']);
        $streetName = mysqli_real_escape_string($kohaConn, $data['StreetName']);
        $progCode = mysqli_real_escape_string($kohaConn, $data['ShortName']);
        $email = mysqli_real_escape_string($kohaConn, $data['PrivateEmail']);
        
        // Default password
        $defaultPassword = "\$2a\$08\$wDNvlD10AzzvZXjVADUGJuGFBhshiorW.mug6.3IRNt./hZMew0OS";

        $sql = "INSERT INTO koha_library.borrowers
        (borrowernumber, cardnumber, surname, firstname, title, othernames, initials, streetnumber, streettype, address, address2, 
        city, state, zipcode, country, email, phone, mobile, fax, emailpro, phonepro, B_streetnumber, B_streettype, B_address, 
        B_address2, B_city, B_state, B_zipcode, B_country, B_email, B_phone, dateofbirth, branchcode, categorycode, dateenrolled,
        dateexpiry, date_renewed, gonenoaddress, lost, debarred, debarredcomment, contactname, contactfirstname, contacttitle,
        borrowernotes, relationship, ethnicity, ethnotes, sex, password, flags, userid, opacnote, contactnote, sort1, sort2, 
        altcontactfirstname, altcontactsurname, altcontactaddress1, altcontactaddress2, altcontactaddress3, altcontactstate, 
        altcontactzipcode, altcontactcountry, altcontactphone, smsalertnumber, sms_provider_id, privacy, privacy_guarantor_fines,
        privacy_guarantor_checkouts, checkprevcheckout, updated_on, lastseen, lang, login_attempts, overdrive_auth_token, 
        anonymized, autorenew_checkouts)
        VALUES
        (NULL, '{$data['studentNumber']}', '{$surname}', '{$firstName}', '{$data['title']}', '{$middleName}', '{$progCode}', '{$streetName}','{$streetName}', 
        '{$streetName}',NULL,'{$data['Town']}', '{$data['Town']}','{$data['PostalCode']}','{$data['Nationality']}', '{$email}', '{$data['MobilePhone']}', 
        '{$data['HomePhone']}', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
        '{$data['DateOfBirth']}', 'NIPA', 'ST', '{$data['EnrolmentDate']}', '{$data['PeriodEndDate']}', NULL, NULL, 0, NULL, NULL, NULL, 
        NULL, NULL, NULL, '{$data['MaritalStatus']}', NULL, NULL, '{$data['Sex']}', '{$defaultPassword}', NULL, '{$data['studentNumber']}', 
        'Welcome to National Institute of Public Administration. Books should be returned on time or before the due date to avoid overdue penalties.',
        NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 
        '{$data['MobilePhone']}', NULL, 1, 0, 0, 'inherit', NULL, NULL, 'default', 0, NULL, 0, 0)";


        // Echo the SQL query for debugging
        echo "<pre>";
        echo "Columns count: " . substr_count($sql, ',') + 1 . "<br>";
        print_r($sql);
        echo "</pre>";

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
