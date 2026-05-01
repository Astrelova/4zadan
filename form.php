<?php
session_start();

// Подключение к БД
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Валидация ФИО
    $fullname = trim($_POST['fullname'] ?? '');
    if ($fullname === '' || strlen($fullname) > 150 || !preg_match('/^[а-яёА-ЯЁa-zA-Z\s\-]+$/u', $fullname)) {
        $errors[] = 'ФИО должно содержать только буквы и пробелы, не длиннее 150 символов.';
    }

    // Валидация телефона
    $phone = trim($_POST['phone'] ?? '');
    if ($phone === '' || !preg_match('/^[\d\+\-\(\)\s]{5,20}$/', $phone)) {
        $errors[] = 'Телефон должен содержать только цифры, +, -, (, ) и пробелы, длиной от 5 до 20 символов.';
    }

    // Валидация email
    $email = trim($_POST['email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный e-mail.';
    }

    // Валидация даты рождения
    $birthdate = $_POST['birthdate'] ?? '';
    if ($birthdate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
        $errors[] = 'Введите корректную дату рождения.';
    }

    // Валидация пола
    $gender = $_POST['gender'] ?? '';
    if (!in_array($gender, ['male', 'female'])) {
        $errors[] = 'Выберите пол.';
    }

    // Валидация языков
    $languages = $_POST['languages'] ?? [];
    if (empty($languages)) {
        $errors[] = 'Выберите хотя бы один язык программирования.';
    } else {
        $allowed = range(1, 12);
        foreach ($languages as $lang) {
            if (!in_array((int)$lang, $allowed)) {
                $errors[] = 'Выбран недопустимый язык программирования.';
                break;
            }
        }
    }

    // Валидация биографии
    $bio = trim($_POST['bio'] ?? '');
    if ($bio === '') {
        $errors[] = 'Заполните биографию.';
    }

    // Валидация чекбокса
    if (!isset($_POST['contract']) || $_POST['contract'] !== '1') {
        $errors[] = 'Необходимо ознакомиться с контрактом.';
    }

    // Если ошибок нет — сохраняем
    if (empty($errors)) {
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
            $success = "Данные успешно сохранены! ID заявки: $applicationId";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Ошибка сохранения: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Результат</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f4f8; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); max-width: 500px; width: 100%; }
        .error { color: #e74c3c; background: #fdf0ef; padding: 10px; border-radius: 6px; margin-bottom: 10px; }
        .success { color: #27ae60; background: #eafaf1; padding: 10px; border-radius: 6px; margin-bottom: 10px; }
        a { color: #3498db; }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
            <p><a href="form.html">← Вернуться к форме</a></p>
        <?php elseif (isset($success)): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
            <p><a href="form.html">← Заполнить ещё раз</a></p>
        <?php else: ?>
            <p><a href="form.html">← Перейти к форме</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
