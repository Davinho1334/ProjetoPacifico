// arquivo de utilitários; por enquanto vazio, mas colocado para separar a lógica caso precise
// exemplo: normalizar CPF, validar campos etc.

function formatCPF(cpf){
  return cpf.replace(/\D/g,'');
}

function validarCPF(cpf) {
  cpf = cpf.replace(/\D/g, '');
  if (cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) return false;
  let soma = 0, resto;
  for (let i = 1; i <= 9; i++) soma += parseInt(cpf[i-1]) * (11 - i);
  resto = (soma * 10) % 11;
  if (resto === 10 || resto === 11) resto = 0;
  if (resto !== parseInt(cpf[9])) return false;
  soma = 0;
  for (let i = 1; i <= 10; i++) soma += parseInt(cpf[i-1]) * (12 - i);
  resto = (soma * 10) % 11;
  if (resto === 10 || resto === 11) resto = 0;
  if (resto !== parseInt(cpf[10])) return false;
  return true;
}

// salvar edição de aluno
async function salvarAluno(id){
    const aluno = {
        id: parseInt(id),
        ra: document.getElementById("ra").value.trim(),
        curso: document.getElementById("curso").value.trim(),
        turno: document.getElementById("turno").value.trim(),
        serie: document.getElementById("serie").value.trim(),
        status: document.getElementById("status").value.trim(),
        cargaSemanal: parseInt(document.getElementById("cargaSemanal").value) || 0,
        bolsa: parseFloat(document.getElementById("bolsa").value) || 0
    };

    try{
        const res = await fetch("php/edit_student.php", {
            method: "POST",
            headers: {"Content-Type":"application/json"},
            credentials: 'same-origin', // envia cookie de sessão
            body: JSON.stringify(aluno)
        });
        const json = await res.json();
        console.log(json);
        if(json.success){
            alert("Aluno atualizado com sucesso!");
            fetchStudents(); // recarrega tabela
        } else if(res.status === 401){
            alert("Erro: não autorizado. Faça login novamente.");
        } else {
            alert("Erro: " + (json.error || "não foi possível salvar"));
        }
    } catch(err){
        alert("Erro de rede: " + err.message);
    }
}