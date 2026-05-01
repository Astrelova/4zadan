<?php
session_start();

$host = 'localhost';
$dbname = 'u82689';
$user = 'u82689';
$pass = '5218579';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

$errors = [];
$success = '';
$values = [
    'fullname' => '',
    'phone' => '',
    'email' => '',
    'birthdate' => '',
    'gender' => '',
    'languages' => [],
    'bio' => '',
    'contract' => ''
];

// Загружаем сохранённые значения из Cookies только при GET-запросе
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    foreach ($values as $key => $val) {
        if (isset($_COOKIE['saved_' . $key])) {
            if ($key === 'languages') {
                $values[$key] = explode(',', $_COOKIE['saved_' . $key]);
            } else {
                $values[$key] = $_COOKIE['saved_' . $key];
            }
        }
    }
}

// Загружаем ошибки из Cookies и сразу удаляем их
$errorCookies = [];
foreach (['fullname','phone','email','birthdate','gender','languages','bio','contract'] as $field) {
    if (isset($_COOKIE['error_' . $field])) {
        $errorCookies[$field] = $_COOKIE['error_' . $field];
        setcookie('error_' . $field, '', time() - 3600, '/');
    }
}

// Загружаем введённые значения после ошибки и удаляем
$oldValues = [];
foreach (['fullname','phone','email','birthdate','gender','languages','bio','contract'] as $field) {
    if (isset($_COOKIE['old_' . $field])) {
        $oldValues[$field] = $_COOKIE['old_' . $field];
        setcookie('old_' . $field, '', time() - 3600, '/');
    }
}

// Если есть ошибки — показываем только то, что ввёл пользователь
if (!empty($errorCookies)) {
    $values['fullname'] = $oldValues['fullname'] ?? '';
    $values['phone'] = $oldValues['phone'] ?? '';
    $values['email'] = $oldValues['email'] ?? '';
    $values['birthdate'] = $oldValues['birthdate'] ?? '';
    $values['gender'] = $oldValues['gender'] ?? '';
    $values['languages'] = isset($oldValues['languages']) ? explode(',', $oldValues['languages']) : [];
    $values['bio'] = $oldValues['bio'] ?? '';
    $values['contract'] = $oldValues['contract'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Валидация ФИО
    $fullname = trim($_POST['fullname'] ?? '');
    if ($fullname === '' || strlen($fullname) > 150) {
        $errors['fullname'] = 'ФИО обязательно, не длиннее 150 символов.';
    } elseif (!preg_match('/^[а-яёА-ЯЁa-zA-Z\s\-]+$/u', $fullname)) {
        $errors['fullname'] = 'Допустимые символы: буквы, пробелы, дефис.';
    }

    // Валидация телефона
    $phone = trim($_POST['phone'] ?? '');
    if ($phone === '') {
        $errors['phone'] = 'Телефон обязателен.';
    } elseif (!preg_match('/^[\d\+\-\(\)\s]{5,20}$/', $phone)) {
        $errors['phone'] = 'Допустимые символы: цифры, +, -, (, ), пробелы. Длина 5-20.';
    }

    // Валидация email
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $errors['email'] = 'E-mail обязателен.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный e-mail.';
    }

    // Валидация даты
    $birthdate = $_POST['birthdate'] ?? '';
    if ($birthdate === '') {
        $errors['birthdate'] = 'Дата рождения обязательна.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
        $errors['birthdate'] = 'Неверный формат даты.';
    }

    // Валидация пола
    $gender = $_POST['gender'] ?? '';
    if (!in_array($gender, ['male', 'female'])) {
        $errors['gender'] = 'Необходимо выбрать пол.';
    }

    // Валидация языков
    $languages = $_POST['languages'] ?? [];
    if (empty($languages)) {
        $errors['languages'] = 'Выберите хотя бы один язык.';
    } else {
        $allowed = range(1, 12);
        foreach ($languages as $lang) {
            if (!in_array((int)$lang, $allowed)) {
                $errors['languages'] = 'Выбран недопустимый язык.';
                break;
            }
        }
    }

    // Валидация биографии
    $bio = trim($_POST['bio'] ?? '');
    if ($bio === '') {
        $errors['bio'] = 'Биография обязательна.';
    } elseif (!preg_match('/^[а-яёА-ЯЁa-zA-Z0-9\s\-\.\,\!\?\"\'\(\)]+$/u', $bio)) {
        $errors['bio'] = 'Допустимые символы: буквы, цифры, пробелы, знаки препинания.';
    }

    // Валидация чекбокса
    if (!isset($_POST['contract']) || $_POST['contract'] !== '1') {
        $errors['contract'] = 'Необходимо ознакомиться с контрактом.';
    }

    // Если ошибки — сохраняем в Cookies
    if (!empty($errors)) {
        foreach ($errors as $field => $msg) {
            setcookie('error_' . $field, $msg, 0, '/');
        }
        setcookie('old_fullname', $fullname, 0, '/');
        setcookie('old_phone', $phone, 0, '/');
        setcookie('old_email', $email, 0, '/');
        setcookie('old_birthdate', $birthdate, 0, '/');
        setcookie('old_gender', $gender, 0, '/');
        setcookie('old_languages', implode(',', $languages), 0, '/');
        setcookie('old_bio', $bio, 0, '/');
        setcookie('old_contract', isset($_POST['contract']) ? '1' : '', 0, '/');

        header('Location: form.php');
        exit;
    }

    // Сохраняем в БД
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO application (fullname, phone, email, birthdate, gender, bio, contract_accepted) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$fullname, $phone, $email, $birthdate, $gender, $bio]);
        $applicationId = $pdo->lastInsertId();

        $stmtLang = $pdo->prepare("INSERT INTO application_language (application_id, language_id) VALUES (?, ?)");
        foreach ($languages as $langId) {
            $stmtLang->execute([$applicationId, (int)$langId]);
        }
        $pdo->commit();

        // Очищаем старые cookies
        foreach (['old_fullname','old_phone','old_email','old_birthdate','old_gender','old_languages','old_bio','old_contract'] as $c) {
            setcookie($c, '', time() - 3600, '/');
        }

        // Сохраняем значения на 1 год
        $year = time() + 365 * 24 * 3600;
        setcookie('saved_fullname', $fullname, $year, '/');
        setcookie('saved_phone', $phone, $year, '/');
        setcookie('saved_email', $email, $year, '/');
        setcookie('saved_birthdate', $birthdate, $year, '/');
        setcookie('saved_gender', $gender, $year, '/');
        setcookie('saved_languages', implode(',', $languages), $year, '/');
        setcookie('saved_bio', $bio, $year, '/');
        setcookie('saved_contract', '1', $year, '/');

        $success = "Данные успешно сохранены! ID заявки: $applicationId";
        $values = [
            'fullname' => '',
            'phone' => '',
            'email' => '',
            'birthdate' => '',
            'gender' => '',
            'languages' => [],
            'bio' => '',
            'contract' => ''
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        $errors['db'] = 'Ошибка сохранения: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Форма заявки</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f4f8; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .container { background: #fff; padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); max-width: 600px; width: 100%; }
        h1 { text-align: center; color: #2c3e50; margin-bottom: 20px; }
        .error-msg { color: #e74c3c; font-size: 13px; margin-bottom: 5px; }
        .success-msg { color: #27ae60; background: #eafaf1; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center; }
        label { display: block; margin-bottom: 4px; font-weight: 600; color: #34495e; }
        input[type="text"], input[type="tel"], input[type="email"], input[type="date"], textarea, select { width: 100%; padding: 12px; margin-bottom: 10px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px; transition: border 0.3s; }
        input:focus, textarea:focus, select:focus { border-color: #3498db; outline: none; }
        input.error, textarea.error, select.error { border-color: #e74c3c !important; background: #fff5f5 !important; }
        textarea { resize: vertical; min-height: 100px; }
        .radio-group { margin-bottom: 10px; }
        .radio-group label { display: inline-block; margin-right: 20px; font-weight: normal; }
        .checkbox-group { margin-bottom: 15px; }
        .checkbox-group label { display: inline; font-weight: normal; }
        button { width: 100%; padding: 14px; background: #3498db; color: #fff; border: none; border-radius: 8px; font-size: 18px; cursor: pointer; transition: background 0.3s; }
        button:hover { background: #2980b9; }
        select[multiple] { height: 150px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Заявка</h1>

        <?php if ($success): ?>
            <div class="success-msg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form action="form.php" method="POST">

            <label for="fullname">ФИО:</label>
            <?php if (isset($errorCookies['fullname'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errorCookies['fullname']) ?></div>
            <?php endif; ?>
            <input type="text" id="fullname" name="fullname" maxlength="150" value="<?= htmlspecialchars($values['fullname'] ?? '') ?>" class="<?= isset($errorCookies['fullname']) ? 'error' : '' ?>">

            <label for="phone">Телефон:</label>
            <?php if (isset($errorCookies['phone'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errorCookies['phone']) ?></div>
            <?php endif; ?>
            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($values['phone'] ?? '') ?>" class="<?= isset($errorCookies['phone']) ? 'error' : '' ?>">

            <label for="email">E-mail:</label>
            <?php if (isset($errorCookies['email'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errorCookies['email']) ?></div>
            <?php endif; ?>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($values['email'] ?? '') ?>" class="<?= isset($errorCookies['email']) ? 'error' : '' ?>">

            <label for="birthdate">Дата рождения:</label>
            <?php if (isset($errorCookies['birthdate'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errorCookies['birthdate']) ?></div>
            <?php endif; ?>
            <input type="date" id="birthdate" name="birthdate" value="<?= htmlspecialchars($values['birthdate'] ?? '') ?>" class="<?= isset($errorCookies['birthdate']) ? 'error' : '' ?>">

            <label>Пол:</label>
            <?php if (isset($errorCookies['gender'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errorCookies['gender']) ?></div>
            <?php endif; ?>
            <div class="radio-group">
                <input type="radio" id="male" name="gender" value="male" <?= ($values['gender'] ?? '') === 'male' ? 'checked' : '' ?>>
                <label for="male">Мужской</label>
                <input type="radio" id="female" name="gender" value="female" <?= ($values['gender'] ?? '') === 'female' ? 'checked' : '' ?>>
                <label for="female">Женский</label>
            </div>

            <label for="languages">Любимый язык программирования:</label>
            <?php if (isset($errorCookies['languages'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errorCookies['languages']) ?></div>
            <?php endif; ?>
            <select id="languages" name="languages[]" multiple class="<?= isset($errorCookies['languages']) ? 'error' : '' ?>">
                <?php
                $langs = [1=>'Pascal',2=>'C',3=>'C++',4=>'JavaScript',5=>'PHP',6=>'Python',7=>'Java',8=>'Haskell',9=>'Clojure',10=>'Prolog',11=>'Scala',12=>'Go'];
                $selectedLangs = $values['languages'] ?? [];
                foreach ($langs as $id => $name):
                    $sel = in_array($id, $selectedLangs) ? 'selected' : '';
                ?>
                    <option value="<?= $id ?>" <?= $sel ?>><?= $name ?></option>
                <?php endforeach; ?>
            </select>

            <label for="bio">Биография:</label>
            <?php if (isset($errorCookies['bio'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errorCookies['bio']) ?></div>
            <?php endif; ?>
            <textarea id="bio" name="bio" class="<?= isset($errorCookies['bio']) ? 'error' : '' ?>"><?= htmlspecialchars($values['bio'] ?? '') ?></textarea>

            <div class="checkbox-group">
                <input type="checkbox" id="contract" name="contract" value="1" <?= ($values['contract'] ?? '') === '1' ? 'checked' : '' ?>>
                <label for="contract">С контрактом ознакомлен(а)</label>
            </div>
            <?php if (isset($errorCookies['contract'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errorCookies['contract']) ?></div>
            <?php endif; ?>

            <button type="submit">Сохранить</button>
        </form>
    </div>
</body>
</html>
