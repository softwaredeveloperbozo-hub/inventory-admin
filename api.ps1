$base = "http://127.0.0.1:8000/api"

function Login($email = "admin@teste.com", $password = "12345678") {
  $body = @{ email = $email; password = $password } | ConvertTo-Json
  $r = Invoke-RestMethod -Method Post -Uri "$base/auth/login" -ContentType "application/json" -Body $body
  $script:h = @{ Authorization = "Bearer $($r.access_token)"; Accept = "application/json" }
  "Login ok: $($r.user.name) ($($r.user.role))"
}

function Api($method, $path, $body = $null) {
  $params = @{ Method = $method; Uri = "$base/$path"; Headers = $script:h }
  if ($body) {
    $params.Body = [System.Text.Encoding]::UTF8.GetBytes($body)
    $params.ContentType = "application/json; charset=utf-8"
  }
  try {
    $res = Invoke-RestMethod @params
    if ($null -eq $res -or $res -eq "") { "OK (sem conteudo)" } else { $res | ConvertTo-Json -Depth 8 }
  } catch {
    "STATUS: " + $_.Exception.Response.StatusCode.value__
    (New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())).ReadToEnd()
  }
}

function Id($path, $body) {
  (Invoke-RestMethod -Method Post -Uri "$base/$path" -Headers $script:h -ContentType "application/json; charset=utf-8" -Body ([System.Text.Encoding]::UTF8.GetBytes($body))).data.id
}

function Download($path, $file) {
  try {
    Invoke-WebRequest -UseBasicParsing -Uri "$base/$path" -Headers $script:h -OutFile $file
    "Salvo: $file ($((Get-Item $file).Length) bytes)"
  } catch {
    "STATUS: " + $_.Exception.Response.StatusCode.value__
  }
}