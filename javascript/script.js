// utils --------------------------------------------------------------
function formatCPF(cpf){ return (cpf || '').replace(/\D/g,''); }

function validarCPF(cpf) {
  cpf = (cpf || '').replace(/\D/g, '');
  if (cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) return false;
  let soma = 0, resto;
  for (let i = 1; i <= 9; i++) soma += parseInt(cpf[i-1]) * (11 - i);
  resto = (soma * 10) % 11; if (resto === 10 || resto === 11) resto = 0;
  if (resto !== parseInt(cpf[9])) return false;
  soma = 0;
  for (let i = 1; i <= 10; i++) soma += parseInt(cpf[i-1]) * (12 - i);
  resto = (soma * 10) % 11; if (resto === 10 || resto === 11) resto = 0;
  if (resto !== parseInt(cpf[10])) return false;
  return true;
}

// helpers DOM seguros ------------------------------------------------
function el(id){ return document.getElementById(id); }
function val(id){
  const $ = el(id);
  if (!$) return null;
  if ($.tagName === 'SELECT' || $.tagName === 'INPUT' || $.tagName === 'TEXTAREA') return $.value;
  return null;
}
function intval(x){ const n = parseInt(x,10); return Number.isFinite(n) ? n : null; }

// salvar edição de aluno ---------------------------------------------
// OBS: essa função é usada na tela de ADMIN (editar aluno).
// Em páginas que não tiverem certos campos, eles serão ignorados com segurança.
async function salvarAluno(id){
  const payload = {
    id: parseInt(id, 10)
  };

  // campos textuais
  const camposTxt = ['ra','curso','turno','serie','status','contato_aluno','relatorio','observacao','tipo_contrato'];
  camposTxt.forEach(k => { const v = val(k); if (v !== null) payload[k] = v.trim(); });

  // campos numéricos
  const carga = val('cargaSemanal'); if (carga !== null) payload.cargaSemanal = intval(carga) || 0;
  const idade = val('idade');        if (idade !== null) payload.idade = intval(idade);

  // datas / selects adicionais
  const inicio = val('inicio_trabalho'); if (inicio !== null) payload.inicio_trabalho = inicio || null;
  const fim    = val('fim_trabalho');    if (fim !== null)    payload.fim_trabalho = fim || null;

  const renovou = val('renovou_contrato');
  if (renovou !== null) payload.renovou_contrato = intval(renovou) || 0;

  const empresaId = val('empresa_id') || val('edit_empresa'); // compatibilidade
  if (empresaId !== null) payload.empresa_id = empresaId ? intval(empresaId) : null;

  // NOVO: recebeu_bolsa (apenas se existir o select na tela)
  const rb = val('recebeu_bolsa') || val('edit_recebeu_bolsa');
  if (rb !== null) {
    // '' => null | '1' => 1 | '0' => 0
    payload.recebeu_bolsa = (rb === '' ? null : intval(rb));
  }

  // envio ------------------------------------------------------------
  try{
    const res = await fetch("php/edit_student.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify(payload)
    });

    const raw = await res.text();
    let json;
    try { json = JSON.parse(raw); } catch {
      alert("Resposta inesperada do servidor:\n" + raw.slice(0,400));
      return;
    }

    if (res.ok && json.success){
      alert("Aluno atualizado com sucesso!");
      if (typeof fetchStudents === 'function') fetchStudents(); // recarrega tabela se existir
    } else if (res.status === 401){
      alert("Sessão expirada. Faça login novamente.");
    } else {
      alert("Erro: " + (json.message || json.error || "não foi possível salvar"));
    }
  } catch(err){
    alert("Erro de rede: " + err.message);
  }
}

// exporta para uso inline (se necessário)
window.salvarAluno = salvarAluno;
