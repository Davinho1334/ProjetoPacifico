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


// ===================================================================
// MÁSCARAS (CPF, CNPJ, Telefone (DDD) e CEP)  ------------------------
// ===================================================================

// mantém apenas dígitos
function __onlyDigits__(s){ return String(s || '').replace(/\D/g, ''); }

// CPF -> 000.000.000-00
function formatCPFMask(value){
  const v = __onlyDigits__(value).slice(0, 11);
  if (v.length <= 3) return v;
  if (v.length <= 6) return v.replace(/^(\d{3})(\d+)/, '$1.$2');
  if (v.length <= 9) return v.replace(/^(\d{3})(\d{3})(\d+)/, '$1.$2.$3');
  return v.replace(/^(\d{3})(\d{3})(\d{3})(\d{1,2}).*/, '$1.$2.$3-$4');
}

// CNPJ -> 00.000.000/0000-00
function formatCNPJMask(value){
  const v = __onlyDigits__(value).slice(0, 14);
  if (v.length <= 2)  return v;
  if (v.length <= 5)  return v.replace(/^(\d{2})(\d+)/, '$1.$2');
  if (v.length <= 8)  return v.replace(/^(\d{2})(\d{3})(\d+)/, '$1.$2.$3');
  if (v.length <= 12) return v.replace(/^(\d{2})(\d{3})(\d{3})(\d+)/, '$1.$2.$3/$4');
  return v.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{1,2}).*/, '$1.$2.$3/$4-$5');
}

// CEP -> 00000-000
function formatCEPMask(value){
  const v = __onlyDigits__(value).slice(0, 8);
  if (v.length <= 5) return v;
  return v.replace(/^(\d{5})(\d{1,3}).*/, '$1-$2');
}

// Telefone BR:
// 10 dígitos -> (DD) 1234-5678
// 11 dígitos -> (DD) 91234-5678
function formatPhoneMask(value){
  let v = __onlyDigits__(value);
  // remove código do país se usuário digitar 55/+55
  if (v.startsWith('55') && v.length > 10) v = v.slice(2);
  v = v.slice(0, 11);
  if (v.length <= 2)  return v;
  if (v.length <= 6)  return v.replace(/^(\d{2})(\d+)/, '($1) $2');             // (DD) 123
  if (v.length <= 10) return v.replace(/^(\d{2})(\d{4})(\d+)/, '($1) $2-$3');   // (DD) 1234-5678
  return v.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');                   // (DD) 91234-5678
}

// aplica máscara mantendo o cursor aproximadamente
function applyMaskToInput(input, formatter){
  const start = input.selectionStart;
  const oldLen = input.value.length;
  input.value = formatter(input.value);
  const newLen = input.value.length;
  const diff = newLen - oldLen;
  const newPos = Math.max(0, (start ?? newLen) + diff);
  try { input.setSelectionRange(newPos, newPos); } catch(e){ /* ignore */ }
}

// inicializa em qualquer página que inclua este script
function initInputMasks(){
  const $ = (sel)=>Array.from(document.querySelectorAll(sel));

  // CPF
  const cpfInputs = $('input[name="cpf"], input[data-mask="cpf"]');
  cpfInputs.forEach(input=>{
    input.setAttribute('maxlength','14'); // 000.000.000-00
    input.addEventListener('input', ()=>applyMaskToInput(input, formatCPFMask));
    input.addEventListener('blur',  ()=>{ input.value = formatCPFMask(input.value); });
  });

  // CNPJ
  const cnpjInputs = $('input[name="cnpj"], input[data-mask="cnpj"]');
  cnpjInputs.forEach(input=>{
    input.setAttribute('maxlength','18'); // 00.000.000/0000-00
    input.addEventListener('input', ()=>applyMaskToInput(input, formatCNPJMask));
    input.addEventListener('blur',  ()=>{ input.value = formatCNPJMask(input.value); });
  });

  // CEP
  const cepInputs = $('input[name="cep"], input[data-mask="cep"]');
  cepInputs.forEach(input=>{
    input.setAttribute('maxlength','9'); // 00000-000
    input.addEventListener('input', ()=>applyMaskToInput(input, formatCEPMask));
    input.addEventListener('blur',  ()=>{ input.value = formatCEPMask(input.value); });
  });

  // Telefone (DDD)
  const phoneInputs = $('input[name="contato_aluno"], input[name="tel"], input[name="phone"], input[data-mask="phone"]');
  phoneInputs.forEach(input=>{
    input.setAttribute('maxlength','15'); // (00) 90000-0000
    input.addEventListener('input', ()=>applyMaskToInput(input, formatPhoneMask));
    input.addEventListener('blur',  ()=>{ input.value = formatPhoneMask(input.value); });
  });
}

// roda quando o DOM estiver pronto
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initInputMasks);
} else {
  initInputMasks();
}
