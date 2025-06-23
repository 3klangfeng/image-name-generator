<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    
    function validate_id_number($id_number) {
        if (strlen($id_number) !== 18 || !preg_match('/^\d{17}[\dX]$/', $id_number)) {
            return false;
        }
        $weights = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $check_codes = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += intval($id_number[$i]) * $weights[$i];
        }
        return $id_number[17] === $check_codes[$sum % 11];
    }

    function extract_birth_date($id_number) {
        try {
            $birth_date_str = substr($id_number, 6, 8);
            $birth_date = DateTime::createFromFormat('Ymd', $birth_date_str);
            if (!$birth_date || $birth_date->format('Ymd') !== $birth_date_str) {
                return null;
            }
            return $birth_date;
        } catch (Exception $e) {
            return null;
        }
    }

    function calculate_age($birth_date) {
        $today = new DateTime();
        $diff = $birth_date->diff($today);
        return $diff->y - ($today < new DateTime($today->format('Y') . $birth_date->format('-m-d')) ? 1 : 0);
    }

    $postData = file_get_contents('php://input');
    $data = json_decode($postData, true);
    if (!$data) {
        $data = $_POST;
    }
    
    $id_number = filter_var($data['id_number'] ?? '', FILTER_SANITIZE_STRING);
    
    if (!validate_id_number($id_number)) {
        http_response_code(400);
        echo json_encode(['error' => '请输入有效的18位身份证号码'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $birth_date = extract_birth_date($id_number);
    if (!$birth_date) {
        http_response_code(400);
        echo json_encode(['error' => '身份证号码中的出生日期无效'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $age = calculate_age($birth_date);
    
    $suffixes = [
        "",
        "_寸照",
        "_身份证",
        "_身份证正面",
        "_身份证反面",
        "_户口本",
        "_学历证明",
        "_健康承诺书",
        "_取证申请表",
        "_证书",
        "_证书正面",
        "_证书反面"
    ];
    
    $generated_ids = array_map(function($suffix) use ($id_number) {
        return $id_number . $suffix;
    }, $suffixes);
    
    http_response_code(200);
    echo json_encode([
        'ids' => $generated_ids,
        'age' => $age
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>身份证号码生成器</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            width: 80%;
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            margin-bottom: 10px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #45a049;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }
        .error {
            color: red;
            margin-top: 10px;
            padding: 10px;
            background-color: #fff0f0;
            border-radius: 4px;
            display: none;
        }
        .id-table {
            width: 100%;
            border-collapse: collapse;
        }
        .id-cell {
            padding: 8px;
            width: 70%;
        }
        .button-cell {
            padding: 8px;
            text-align: right;
        }
        .copy-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .copy-btn:hover {
            background-color: #0056b3;
        }
        .copy-success {
            background-color: #28a745 !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>图片名生成器</h1>
        <div class="form-group">
            <form id="idForm" aria-label="身份证号码输入表单">
                <input type="text" name="id_number" placeholder="请输入18位身份证号码" required aria-required="true" aria-label="身份证号码">
                <button type="submit">生成</button>
            </form>
        </div>
        <div id="error" class="error" role="alert" aria-live="assertive"></div>
        <div id="result" class="result" style="display: none;" role="region" aria-live="polite"></div>
    </div>

    <script>
        document.getElementById('idForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const idNumber = this.elements['id_number'].value.trim();
            const errorDiv = document.getElementById('error');
            const resultDiv = document.getElementById('result');
            
            if (idNumber.length !== 18 || !/^\d{17}[\dX]$/.test(idNumber)) {
                errorDiv.textContent = '请输入有效的18位身份证号码';
                errorDiv.style.display = 'block';
                resultDiv.style.display = 'none';
                return;
            }
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id_number: idNumber
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('请求失败');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    errorDiv.textContent = data.error;
                    errorDiv.style.display = 'block';
                    resultDiv.style.display = 'none';
                } else {
                    errorDiv.style.display = 'none';
                    let resultHtml = `<h3>年龄: ${data.age}</h3><table class="id-table">`;
                    data.ids.forEach(id => {
                        resultHtml += `<tr>
                            <td class="id-cell">${id}</td>
                            <td class="button-cell">
                                <button class="copy-btn" onclick="copyText('${id}', this)">复制</button>
                            </td>
                        </tr>`;
                    });
                    resultHtml += '</table>';
                    resultDiv.innerHTML = resultHtml;
                    resultDiv.style.display = 'block';
                    this.reset();
                }
            })
            .catch(error => {
                errorDiv.textContent = error.message || '请求失败，请稍后重试';
                errorDiv.style.display = 'block';
                resultDiv.style.display = 'none';
                console.error('Error:', error);
            });
        });

        function copyText(text, button) {
            const copy = async () => {
                try {
                    await navigator.clipboard.writeText(text);
                    const originalText = button.textContent;
                    button.textContent = '已复制';
                    button.classList.add('copy-success');
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.classList.remove('copy-success');
                    }, 2000);
                } catch (err) {
                    console.error('复制失败:', err);
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand('copy');
                        const originalText = button.textContent;
                        button.textContent = '已复制';
                        button.classList.add('copy-success');
                        setTimeout(() => {
                            button.textContent = originalText;
                            button.classList.remove('copy-success');
                        }, 2000);
                    } catch (e) {
                        alert('复制失败，请手动复制');
                    }
                    document.body.removeChild(textarea);
                }
            };
            copy();
        }
    </script>
</body>
</html>