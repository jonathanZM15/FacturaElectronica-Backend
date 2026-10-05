import re

filepath = r"C:\Users\CompuStore\Desktop\dos sistemas\tesis\FacturaElectronica-Backend\app\Http\Controllers\FacturacionController.php"

with open(filepath, 'r', encoding='utf-8') as f:
    content = f.read()

# Extract emitirNotaCredito
start_idx = content.find("public function emitirNotaCredito(Request $request): JsonResponse")
end_idx = content.find("public function estadoComprobante(Comprobante $comprobante): JsonResponse")

if start_idx != -1 and end_idx != -1:
    emitir_nc = content[start_idx:end_idx]
    
    emitir_nd = emitir_nc.replace("emitirNotaCredito", "emitirNotaDebito")
    emitir_nd = emitir_nd.replace("nextSecuencialNotaCredito", "nextSecuencialNotaDebito")
    emitir_nd = emitir_nd.replace("'NOTA_CREDITO'", "'NOTA_DEBITO'")
    
    # Insert emitir_nd right before estadoComprobante
    new_content = content[:end_idx] + emitir_nd + content[end_idx:]
    
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(new_content)
    print("Method duplicated successfully.")
else:
    print("Could not find method boundaries.")
